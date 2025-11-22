# Dateien im Release-Paket (we-spamfighter.zip)

Diese Dateien werden im Release-ZIP-Paket enthalten sein, das von GitHub Actions automatisch erstellt wird.

## 📦 Enthaltene Dateien

### Haupt-Plugin-Dateien

- ✅ `we-spamfighter.php` - Haupt-Plugin-Datei
- ✅ `uninstall.php` - Uninstall-Script
- ✅ `README.md` - Plugin-Dokumentation (explizit eingefügt)
- ✅ `LICENSE` - GPL v2 Lizenz (explizit eingefügt)

### Plugin-Struktur

- ✅ `includes/` - Alle Plugin-Klassen
  - ✅ `includes/admin/` - Admin-Klassen
  - ✅ `includes/core/` - Core-Klassen (Database, Logger, Notifications, Updater)
  - ✅ `includes/detection/` - Spam-Erkennungs-Klassen
  - ✅ `includes/integration/` - Integration-Klassen (CF7, Comments)

### Assets

- ✅ `assets/css/` - Stylesheets
  - ✅ `admin.css`
  - ✅ `dashboard.css`
  - ✅ `frontend.css`
  - ✅ `frontend.min.css`
- ✅ `assets/js/` - JavaScript-Dateien
  - ✅ `admin.js`
  - ✅ `dashboard.js`
  - ✅ `frontend.js`
  - ✅ `frontend.min.js`

### Übersetzungen

- ✅ `languages/` - Alle Übersetzungsdateien
  - ✅ `we-spamfighter-de_DE.l10n.php`
  - ✅ `we-spamfighter-de_DE.mo`
  - ✅ `we-spamfighter-de_DE.po`
  - ✅ `we-spamfighter.pot`

## ❌ Ausgeschlossene Dateien

### Entwicklungsdateien

- ❌ `.git/` - Git-Repository
- ❌ `.github/` - GitHub Actions Workflows
- ❌ `.gitignore` - Git-Ignore-Datei
- ❌ `.editorconfig` - Editor-Konfiguration
- ❌ `.phpcs.xml` - PHP CodeSniffer Config

### Build-Tools & Dependencies

- ❌ `node_modules/` - Node.js Dependencies
- ❌ `vendor/` - Composer Dependencies
- ❌ `package.json` - npm Konfiguration
- ❌ `package-lock.json` - npm Lock-Datei
- ❌ `composer.json` - Composer Konfiguration
- ❌ `composer.lock` - Composer Lock-Datei

### Scripts & Tests

- ❌ `scripts/` - Build- und Release-Scripts
- ❌ `bin/` - Binär-Dateien/Scripts
- ❌ `tests/` - Test-Dateien

### Dokumentation (außer README.md)

- ❌ `CHANGELOG.md` - Changelog (nur für GitHub)
- ❌ `RELEASE.md` - Release-Dokumentation
- ❌ `RELEASE-FILES.md` - Diese Datei
- ❌ Alle anderen `*.md` Dateien

### System-Dateien

- ❌ `.DS_Store` - macOS System-Dateien
- ❌ `Thumbs.db` - Windows Vorschaubilder

## 📊 Beispiel-Struktur des Release-ZIPs

```
we-spamfighter/
├── we-spamfighter.php
├── uninstall.php
├── README.md
├── LICENSE
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   ├── dashboard.css
│   │   ├── frontend.css
│   │   └── frontend.min.css
│   └── js/
│       ├── admin.js
│       ├── dashboard.js
│       ├── frontend.js
│       └── frontend.min.js
├── includes/
│   ├── admin/
│   │   ├── class-dashboard.php
│   │   └── class-settings.php
│   ├── core/
│   │   ├── class-database.php
│   │   ├── class-logger.php
│   │   ├── class-notifications.php
│   │   └── class-updater.php
│   ├── detection/
│   │   └── class-open-ai.php
│   └── integration/
│       ├── class-comments.php
│       └── class-contact-form-7.php
└── languages/
    ├── we-spamfighter-de_DE.l10n.php
    ├── we-spamfighter-de_DE.mo
    ├── we-spamfighter-de_DE.po
    └── we-spamfighter.pot
```

## ✅ Wichtig

- **README.md** und **LICENSE** werden explizit eingefügt (trotz `--exclude='*.md'`)
- Alle **minifizierten Dateien** (.min.css, .min.js) sind enthalten
- Alle **Übersetzungsdateien** sind enthalten
- Nur **Produktions-Dateien** sind enthalten (keine Dev-Dependencies)

## 🔄 Anpassen

Wenn du Dateien hinzufügen oder ausschließen möchtest, bearbeite:

- `.github/workflows/release.yml` → Schritt "Create plugin ZIP"
