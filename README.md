# Drupal AI Fabric Integration (`ai_fabric`)

The **AI Fabric Integration** module connects Daniel Miessler's **Fabric** patterns into the Drupal AI subsystem ecosystem. It synchronizes local pattern directories as custom Drupal configuration entities (`fabric_pattern`), handles customization conflicts gracefully, and allows exporting local database changes back to the filesystem (Contrib Back).

---

## 🚀 Key Features

* **Config Entity Integration**: Automatically registers Fabric patterns as Drupal config entities, standardizing prompt management across staging/production environments.
* **Aesthetic Admin Listing**: Renders an intuitive list view highlighting the synchronization state of every pattern (`Synced`, `Customized Locally`, `Local Only`).
* **Conflict Resolution Engine**: Utilizes SHA-256 hash parity checks of prompt files. If a pattern is edited directly in Drupal's administrative UI, the sync engine flags it as customized and protects it from being overwritten during subsequent imports (unless forced).
* **Upstream Contrib Mode**: Exports Drupal-created or modified patterns back into the filesystem pattern format, preparing Git-ready folders for upstream contributions.
* **Modern CLI Integration**: Implements fully autowired Drush 11/12 commands with progress tracking and interactive Git assistance.

---

## 🛠️ Installation

1. Copy this module folder into your Drupal site's `modules/custom/ai_fabric` directory.
2. Enable the module using Drush:
   ```bash
   drush pm:enable ai_fabric
   ```
3. Ensure you have the core `ai` module installed and configured.

---

## 📖 CLI Usage

### 1. Synchronize Patterns to Drupal
Reads patterns from a local directory (or cloned Fabric repo) and creates/updates config entities in Drupal.
```bash
drush ai-fabric:sync /path/to/fabric
```
To force overwrite locally customized entities in Drupal:
```bash
drush ai-fabric:sync /path/to/fabric --force
```

### 2. Export Patterns back to Filesystem (Contrib Back)
Finds new or customized patterns inside Drupal and writes them back into the `/patterns/[pattern-name]/system.md` file format on disk.
```bash
drush ai-fabric:contrib /path/to/fabric
```

---

## 🧪 Testing and Verification

This module includes a comprehensive test suite of unit and kernel integration tests adhering to Drupal's senior development guidelines.

### Running Tests in a DDEV Environment

To run the test suite inside a DDEV-backed Drupal project:

1. **Verify PHPUnit is configured inside DDEV:**
   Make sure you have your `phpunit.xml` set up inside your Drupal root. If not, DDEV can generate one, or you can copy the core template:
   ```bash
   ddev exec cp core/tests/phpunit.xml.dist core/phpunit.xml
   ```

2. **Execute Unit Tests:**
   ```bash
   ddev exec vendor/bin/phpunit web/modules/custom/ai_fabric/tests/src/Unit/
   ```

3. **Execute Kernel Tests:**
   Kernel tests boot an active database backend and container services.
   ```bash
   ddev exec vendor/bin/phpunit web/modules/custom/ai_fabric/tests/src/Kernel/
   ```
