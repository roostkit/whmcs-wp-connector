<?php
/**
 * Tests for the Crypto service.
 *
 * @package RoostKit\WhmcsConnector\Tests\Unit\Security
 */

declare(strict_types=1);

namespace RoostKit\WhmcsConnector\Tests\Unit\Security;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RoostKit\WhmcsConnector\Security\Crypto;

class CryptoTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'esc_html__' )->returnArg();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_encrypt_decrypt_round_trip(): void {
		$crypto    = new Crypto();
		$plaintext = 'my-secret-api-key-12345';

		$encrypted = $crypto->encrypt( $plaintext );
		$decrypted = $crypto->decrypt( $encrypted );

		$this->assertSame( $plaintext, $decrypted );
		$this->assertNotSame( $plaintext, $encrypted );
	}

	public function test_encrypt_empty_string_returns_empty(): void {
		$crypto = new Crypto();

		$this->assertSame( '', $crypto->encrypt( '' ) );
		$this->assertSame( '', $crypto->decrypt( '' ) );
	}

	public function test_each_encryption_produces_different_ciphertext(): void {
		$crypto    = new Crypto();
		$plaintext = 'same-input';

		$encrypted1 = $crypto->encrypt( $plaintext );
		$encrypted2 = $crypto->encrypt( $plaintext );

		// Different nonces should produce different ciphertext.
		$this->assertNotSame( $encrypted1, $encrypted2 );

		// Both should decrypt to the same value.
		$this->assertSame( $plaintext, $crypto->decrypt( $encrypted1 ) );
		$this->assertSame( $plaintext, $crypto->decrypt( $encrypted2 ) );
	}

	public function test_decrypt_with_corrupted_data_throws(): void {
		$crypto = new Crypto();

		$this->expectException( \RuntimeException::class );

		$crypto->decrypt( 'not-valid-base64-ciphertext!!!' );
	}

	public function test_decrypt_with_tampered_ciphertext_throws(): void {
		$crypto    = new Crypto();
		$plaintext = 'sensitive-data';

		$encrypted = $crypto->encrypt( $plaintext );

		// Tamper with the ciphertext.
		$tampered = substr( $encrypted, 0, -4 ) . 'XXXX';

		$this->expectException( \RuntimeException::class );
		$crypto->decrypt( $tampered );
	}

	public function test_sodium_is_available(): void {
		$crypto = new Crypto();

		// On PHP 8.1+, sodium should always be available.
		$this->assertTrue( $crypto->is_sodium_available() );
	}

	public function test_handles_special_characters_in_plaintext(): void {
		$crypto = new Crypto();

		$special = 'p@ssw0rd!#%^&*()_+-=[]{}|;\':",./<>?`~' . "\n\t";
		$encrypted = $crypto->encrypt( $special );
		$decrypted = $crypto->decrypt( $encrypted );

		$this->assertSame( $special, $decrypted );
	}

	public function test_handles_long_plaintext(): void {
		$crypto = new Crypto();

		$long = str_repeat( 'a', 10000 );
		$encrypted = $crypto->encrypt( $long );
		$decrypted = $crypto->decrypt( $encrypted );

		$this->assertSame( $long, $decrypted );
	}
}
