# WHMCS Connector for WordPress 🔌

> **Connect your WHMCS billing platform to WordPress using the official API — native Gutenberg blocks, client login, and portal shortcuts without iframes or fragile scraping.**

[![License: GPL v2+](https://img.shields.io/badge/License-GPL_v2%2B-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.1-8892BF.svg)](https://www.php.net/)
[![WordPress Version](https://img.shields.io/badge/WordPress-%3E%3D%206.4-21759B.svg)](https://wordpress.org/)
[![Latest Release](https://img.shields.io/github/v/release/roostkit/whmcs-wp-connector?color=brightgreen)](https://github.com/roostkit/whmcs-wp-connector/releases)
[![Open Issues](https://img.shields.io/github/issues/roostkit/whmcs-wp-connector)](https://github.com/roostkit/whmcs-wp-connector/issues)

---

## 🌟 Overview

**WHMCS Connector by RoostKit** bridges your WHMCS billing platform and your WordPress marketing website using the official WHMCS Local API. 

Built specifically for web hosting companies, domain registrars, SaaS businesses, and digital agencies, this plugin allows you to embed client login forms, account navigation menus, and client area links natively into your WordPress theme with zero scraping and zero iframes.

---

## 🚀 Key Features (Free Edition)

* **⚡ Official WHMCS API Integration** — Fetches live data securely via the official WHMCS Local API without page scraping or fragile web requests.
* **🔒 Encrypted Credential Storage** — Your WHMCS API Identifier and Secret are encrypted at rest using high-security `libsodium` cryptography.
* **🧱 Native Gutenberg Blocks**:
  * `whmcs/login-form` — Fully styled customer login form with customizable labels, redirect targets, and active theme typography/color inheritance.
  * `whmcs/client-area` — Modular client navigation grid for quick access to Invoices, Support Tickets, Services, and Knowledgebase.
* **🎨 Block Patterns Ready-to-Use**:
  * *3-Column Modern Hosting Grid* — Clean, conversion-focused layout.
  * *Single Product Feature Highlight* — Horizontal card layout for featured hosting plans.
  * *Client Portal Quick Actions* — Instant dashboard links for authenticated users.
* **🔗 Dynamic Portal Link Anchors** — Easily link navigation items and buttons to `#whmcs-clientarea`, `#whmcs-tickets`, `#whmcs-invoices`, and `#whmcs-knowledgebase`.
* **🚀 Intelligent Dual-Layer Caching** — Non-blocking transient caching with configurable TTL to ensure sub-millisecond page load times.
* **🛡️ Rate-Limited Brute Force Protection** — Built-in IP rate limiter (5 attempts per 10 minutes) protecting client login forms against credential stuffing attacks.

---

## 📋 Requirements

* **WordPress**: 6.4 or higher
* **PHP**: 8.1 or higher (with `sodium`, `openssl`, and `json` extensions enabled)
* **WHMCS**: 8.0 or higher with API credentials enabled (API Identifier + API Secret)
* **HTTPS**: SSL is recommended on your WordPress site for secure credential transmission

---

## 📦 Installation

### Method A: Upload Release ZIP (Recommended)

1. Download the latest `whmcs-connector-free-*.zip` from the [GitHub Releases page](https://github.com/roostkit/whmcs-wp-connector/releases).
2. Log in to your WordPress Admin dashboard.
3. Navigate to **Plugins → Add New Plugin → Upload Plugin**.
4. Choose the downloaded ZIP file and click **Install Now**.
5. Click **Activate Plugin**.

### Method B: Manual / Developer Installation

1. Clone this repository directly into your WordPress plugins folder:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/roostkit/whmcs-wp-connector.git whmcs-connector
   ```
2. Install production dependencies with Composer:
   ```bash
   cd whmcs-connector
   composer install --no-dev --optimize-autoloader
   ```
3. Activate the plugin via **Plugins → Installed Plugins** in your WordPress dashboard or via WP-CLI:
   ```bash
   wp plugin activate whmcs-connector
   ```

---

## ⚙️ Configuration & Getting Started

### 1. Generate WHMCS API Credentials
1. Log in to your WHMCS Admin area.
2. Navigate to **Setup → Staff Management → API Credentials** (or **Configuration → System Settings → API Credentials** in WHMCS 8+).
3. Click **Create API Credentials**.
4. Select your administrative user and generate an **API Identifier** and **API Secret**.
5. Ensure the API role has permissions to read products and authenticate clients.

### 2. Configure WordPress Settings
1. In your WordPress Admin, go to **WHMCS Connector → Settings**.
2. Enter your **WHMCS System URL** (e.g., `https://billing.yourdomain.com`).
3. Enter your **API Identifier** and **API Secret**.
4. Click **Test Connection** to verify API communication.
5. Click **Save Settings**.

### 3. Add Blocks to Your Pages
* In the Block Editor (Gutenberg), click **+ (Add Block)** and search for `WHMCS`.
* Insert the **WHMCS Login Form** or **WHMCS Client Area** block anywhere on your page.
* Customize block colors, headings, and alignments directly from the Block Settings sidebar.

---

## 🖼️ Screenshots

| Settings & API Connection | Client Area Navigation |
| :---: | :---: |
| ![WHMCS Settings Screen](docs/screenshots/settings.png) | ![WHMCS Client Area Block](docs/screenshots/client-area.png) |
| *Encrypted API credentials and connection test* | *Modular client area dashboard links* |

---

## ❓ Frequently Asked Questions (FAQ)

### Does this plugin expose sensitive customer data?
No. All sensitive operations (such as credential decryption and API communication) occur server-side over HTTPS. Passwords and API secrets are never stored in plain text or exposed to frontend scripts.

### Does it work with custom WHMCS themes and templates?
Yes. Because the plugin connects directly to the WHMCS Local API rather than scraping HTML or rendering iframes, it is 100% independent of your WHMCS frontend theme (Twenty-One, Lagom, etc.).

### Does it process payments inside WordPress?
No. For maximum security and PCI-DSS compliance, purchasing and checkout actions redirect customers securely to your WHMCS cart and invoice gateways.

### Is the Pro edition required to use the free features?
No. The Free edition is fully functional and standalone for displaying client logins, account links, and portal navigations.

---

## ⭐ Looking for Advanced Features? Check out Pro!

Upgrade to **[WHMCS Connector Pro](https://roostkit.site/whmcs-connector)** to supercharge your hosting marketing site:

* 📊 **SaaS Pricing Table Block** — 3-column modern SaaS pricing matrix with live Monthly/Annually billing cycle toggle pills.
* 🎛️ **Interactive VPS Resource Slider Block** — Dynamic CPU, RAM, and SSD sliders with real-time price calculation and WHMCS cart configuration mapping.
* 🔍 **AJAX Domain Availability Search** — Live domain search with TLD pill filters.
* 🔗 **Dynamic Checkout Link Interceptor** — Automatically transforms `#whmcs-order-{PID}` into direct WHMCS provisioning URLs.
* 🏷️ **Atomic Price Shortcodes** — `[whmcs_price]`, `[whmcs_name]`, `[whmcs_order_url]` for custom page-builder designs (Elementor, Beaver Builder, Divi).

👉 **[Discover WHMCS Connector Pro at RoostKit.site](https://roostkit.site/whmcs-connector)**

---

## 🤝 Contributing

Contributions, feature suggestions, and pull requests are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code standards and test workflows.

---

## 🛡️ Security Vulnerability Reporting

If you discover a potential security issue within WHMCS Connector, please follow our responsible disclosure guidelines in [SECURITY.md](SECURITY.md). Please do not report security vulnerabilities via public GitHub issues.

---

## 📄 License & Changelog

* **License**: Licensed under the **GNU General Public License v2.0 or later** (GPL-2.0-or-later). See [LICENSE](LICENSE) for details.
* **Changelog**: See [CHANGELOG.md](CHANGELOG.md) for release history and updates.
* **Support**: Have questions or need assistance? Open an issue on our [GitHub Issue Tracker](https://github.com/roostkit/whmcs-wp-connector/issues).
