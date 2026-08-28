# Changelog

All notable changes to WHMCS Connector by RoostKit will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - TBD

### Added
- Initial release of WHMCS Connector (Free/Core).
- Settings screen with encrypted credential storage (libsodium).
- API client for WHMCS Local API with retry logic and error logging.
- `[whmcs_login]` shortcode and Gutenberg block.
- `[whmcs_client_area]` shortcode and Gutenberg block.
- `[whmcs_pricing]` shortcode and Gutenberg block.
- Transient-based caching layer with configurable TTL.
- Pretty permalinks for `/clientarea/` and `/pricing/`.
- Rate-limited login form (5 attempts per 10 minutes per IP).
- Full i18n support with `whmcs-connector` text domain.
