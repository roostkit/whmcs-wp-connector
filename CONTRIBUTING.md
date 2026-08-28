# Contributing to WHMCS Connector

Thank you for your interest in contributing to **WHMCS Connector by RoostKit**! We welcome bug reports, feature suggestions, documentation improvements, and pull requests from the community.

---

## 🛠️ Local Development Setup

### Prerequisites
- **PHP**: 8.1 or higher
- **Composer**: 2.x
- **Node.js**: 20.x

### Setup Steps
1. Fork and clone the repository:
   ```bash
   git clone https://github.com/your-username/whmcs-wp-connector.git
   cd whmcs-wp-connector
   ```
2. Install development dependencies:
   ```bash
   composer install
   ```

---

## 🧪 Testing & Code Standards

All contributions must adhere to the WordPress Coding Standards and pass our automated test suite before merging.

### Running PHPUnit Tests
```bash
composer run test
```

### Running PHP_CodeSniffer (WordPress CS)
```bash
composer run phpcs
```

### Auto-fixing Formatting Issues
```bash
composer run phpcbf
```

---

## 📋 Pull Request Process

1. Create a descriptive feature branch from `main` (e.g. `feat/custom-button-styles` or `fix/api-timeout-handling`).
2. Ensure all PHPUnit tests pass (`composer test`) and code matches WordPress standards (`composer phpcs`).
3. Add unit test coverage for new functionality or regression tests for bug fixes.
4. Keep PR descriptions clear, concise, and linked to relevant GitHub issues.

---

## 📄 Code of Conduct

Please be respectful, collaborative, and constructive when interacting with maintainers and other contributors.
