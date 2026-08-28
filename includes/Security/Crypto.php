<?php
/**
 * Encryption service for storing API credentials at rest.
 *
 * @package RoostKit\WhmcsConnector\Security
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Security;

/**
 * Encrypts and decrypts sensitive data using libsodium (preferred) or OpenSSL fallback.
 *
 * The encryption key is derived at runtime from WordPress AUTH_KEY and AUTH_SALT
 * constants in wp-config.php. It is never stored in the database.
 */
final class Crypto {

	/**
	 * Check if libsodium is available.
	 *
	 * @return bool
	 */
	public function is_sodium_available(): bool {
		return function_exists( 'sodium_crypto_secretbox' )
			&& defined( 'SODIUM_CRYPTO_SECRETBOX_KEYBYTES' )
			&& defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' );
	}

	/**
	 * Encrypt a plaintext string.
	 *
	 * @param string $plaintext The value to encrypt.
	 * @return string Base64-encoded ciphertext (nonce prepended).
	 *
	 * @throws \RuntimeException If encryption fails.
	 */
	public function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		if ( $this->is_sodium_available() ) {
			return $this->encrypt_sodium( $plaintext );
		}

		return $this->encrypt_openssl( $plaintext );
	}

	/**
	 * Decrypt a ciphertext string.
	 *
	 * @param string $ciphertext Base64-encoded ciphertext.
	 * @return string The decrypted plaintext.
	 *
	 * @throws \RuntimeException If decryption fails.
	 */
	public function decrypt( string $ciphertext ): string {
		if ( '' === $ciphertext ) {
			return '';
		}

		if ( $this->is_sodium_available() ) {
			return $this->decrypt_sodium( $ciphertext );
		}

		return $this->decrypt_openssl( $ciphertext );
	}

	/**
	 * Encrypt using libsodium.
	 *
	 * @param string $plaintext Value to encrypt.
	 * @return string Base64-encoded nonce + ciphertext.
	 */
	private function encrypt_sodium( string $plaintext ): string {
		$key   = $this->derive_key( SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );

		// Wipe key from memory.
		sodium_memzero( $key );

		// Prepend nonce to ciphertext for storage.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $nonce . $ciphertext );
	}

	/**
	 * Decrypt using libsodium.
	 *
	 * @param string $encoded Base64-encoded nonce + ciphertext.
	 * @return string Decrypted plaintext.
	 *
	 * @throws \RuntimeException If decryption fails (wrong key, tampered data).
	 */
	private function decrypt_sodium( string $encoded ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$decoded = base64_decode( $encoded, true );
		if ( false === $decoded ) {
			throw new \RuntimeException(
				esc_html__( 'Failed to decode encrypted data.', 'whmcs-connector' )
			);
		}

		$nonce_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		if ( strlen( $decoded ) < $nonce_length ) {
			throw new \RuntimeException(
				esc_html__( 'Encrypted data is too short.', 'whmcs-connector' )
			);
		}

		$nonce      = substr( $decoded, 0, $nonce_length );
		$ciphertext = substr( $decoded, $nonce_length );
		$key        = $this->derive_key( SODIUM_CRYPTO_SECRETBOX_KEYBYTES );

		$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

		// Wipe key from memory.
		sodium_memzero( $key );

		if ( false === $plaintext ) {
			throw new \RuntimeException(
				esc_html__( 'Decryption failed. The data may be corrupted or the encryption key has changed.', 'whmcs-connector' )
			);
		}

		return $plaintext;
	}

	/**
	 * Encrypt using OpenSSL AES-256-GCM (fallback).
	 *
	 * @param string $plaintext Value to encrypt.
	 * @return string Base64-encoded IV + tag + ciphertext.
	 *
	 * @throws \RuntimeException If encryption fails.
	 */
	private function encrypt_openssl( string $plaintext ): string {
		$method = 'aes-256-gcm';
		$key    = $this->derive_key( 32 );
		$iv     = random_bytes( openssl_cipher_iv_length( $method ) );
		$tag    = '';

		$ciphertext = openssl_encrypt( $plaintext, $method, $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $ciphertext ) {
			throw new \RuntimeException(
				esc_html__( 'OpenSSL encryption failed.', 'whmcs-connector' )
			);
		}

		// Pack: IV (12 bytes) + tag (16 bytes) + ciphertext.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return 'ossl:' . base64_encode( $iv . $tag . $ciphertext );
	}

	/**
	 * Decrypt using OpenSSL AES-256-GCM (fallback).
	 *
	 * @param string $encoded Base64-encoded IV + tag + ciphertext.
	 * @return string Decrypted plaintext.
	 *
	 * @throws \RuntimeException If decryption fails.
	 */
	private function decrypt_openssl( string $encoded ): string {
		$method = 'aes-256-gcm';
		$key    = $this->derive_key( 32 );

		// Strip the 'ossl:' prefix.
		$encoded = substr( $encoded, 5 );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$decoded = base64_decode( $encoded, true );
		if ( false === $decoded ) {
			throw new \RuntimeException(
				esc_html__( 'Failed to decode encrypted data.', 'whmcs-connector' )
			);
		}

		$iv_length  = openssl_cipher_iv_length( $method );
		$iv         = substr( $decoded, 0, $iv_length );
		$tag        = substr( $decoded, $iv_length, 16 );
		$ciphertext = substr( $decoded, $iv_length + 16 );

		$plaintext = openssl_decrypt( $ciphertext, $method, $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $plaintext ) {
			throw new \RuntimeException(
				esc_html__( 'Decryption failed.', 'whmcs-connector' )
			);
		}

		return $plaintext;
	}

	/**
	 * Derive an encryption key from WordPress config constants.
	 *
	 * Uses AUTH_KEY and AUTH_SALT from wp-config.php. The key is never stored —
	 * it is derived fresh on every encrypt/decrypt call.
	 *
	 * @param int $length Required key length in bytes.
	 * @return string Raw binary key.
	 */
	private function derive_key( int $length ): string {
		$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'whmcs-connector-default-key';
		$auth_salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'whmcs-connector-default-salt';

		$hash = hash( 'sha256', $auth_key . $auth_salt, true );

		return substr( $hash, 0, $length );
	}
}
