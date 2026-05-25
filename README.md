# Drupal AI Fabric Integration (`ai_fabric`)

The **AI Fabric Integration** module connects Daniel Miessler's **Fabric** patterns into the Drupal AI subsystem ecosystem. It synchronizes local pattern directories as custom Drupal configuration entities (`fabric_pattern`), handles customization conflicts gracefully, allows exporting local database changes back to the filesystem (Contrib Back), and integrates seamlessly with ECA workflows.

---

## 🚀 Key Features

* **Config Entity Integration**: Automatically registers Fabric patterns as Drupal config entities, standardizing prompt management across staging/production environments.
* **Aesthetic Admin Listing**: Renders an intuitive list view highlighting the synchronization state of every pattern (`Synced`, `Customized Locally`, `Local Only`).
* **Administrative Settings & UI**: Configures the module globally at `/admin/config/services/ai-fabric`. Automatically persists settings (like the default filesystem path and force-overwrite behavior) using Drupal's core Configuration System.
* **Conflict Resolution Engine**: Utilizes SHA-256 hash parity checks of prompt files. If a pattern is edited directly in Drupal's administrative UI, the sync engine flags it as customized and protects it from being overwritten during subsequent imports (unless forced).
* **Upstream Contrib Mode**: Exports Drupal-created or modified patterns back into the filesystem pattern format, preparing Git-ready folders for upstream contributions.
* **Modern CLI Integration**: Implements fully autowired Drush 11/12/13 commands with progress tracking, default path fallbacks from Drupal settings, and interactive Git assistance.
* **ECA (Event-Condition-Action) Integration**: Implements a dedicated submodule (`ai_fabric_eca`) that provides the **Run AI Fabric Pattern** action, executing Fabric patterns within ECA automated workflows and storing responses in custom tokens.

---

## 🛠️ Installation

1. Copy this module folder into your Drupal site's `modules/custom/ai_fabric` directory.
2. Enable the main module and the ECA submodule using Drush:
   ```bash
   drush pm:enable ai_fabric ai_fabric_eca
   ```
3. Ensure you have the core `ai` and `eca` modules installed and configured.

---

## ⚙️ Administration & Configuration

### Administrative Settings
Navigate to **Configuration > Web services > AI Fabric Settings** (`/admin/config/services/ai-fabric`) to configure global settings:
* **Fabric Patterns Path**: The absolute filesystem path to your cloned Fabric repository (which contains a `patterns/` subdirectory).
* **Force Overwrite**: The default behavior for overwriting locally modified system prompts during subsequent syncs.

Once saved, these settings are persisted as Drupal configuration and pre-populated into the administrative synchronization form (`/admin/config/services/ai-fabric/sync`).

---

## 📖 CLI Usage

Both CLI commands accept an optional `path` parameter. If no path is provided, they automatically fallback to the global path saved in Drupal's administrative settings.

### 1. Synchronize Patterns to Drupal
Reads patterns from the filesystem and imports them into Drupal as custom entities.
```bash
# Using the default path configured in settings
drush ai-fabric:sync

# Specifying a custom path
drush ai-fabric:sync /path/to/fabric

# Forcing overwrite of locally customized prompts
drush ai-fabric:sync --force
```

### 2. Export Patterns back to Filesystem (Contrib Back)
Finds new or customized patterns inside Drupal and writes them back into the filesystem format.
```bash
# Using the default path configured in settings
drush ai-fabric:contrib

# Specifying a custom path
drush ai-fabric:contrib /path/to/fabric
```

---

## ⚡ ECA Integration (`ai_fabric_eca`)

The included sub-module integrates Fabric patterns into ECA automated workflows. It provides the **Run AI Fabric Pattern** action, which allows you to:
* Select a synchronized Fabric pattern.
* Pass dynamic context (such as entity tokens or custom text) as user input.
* Map the output to a custom ECA token for use in downstream actions (e.g., sending an email, updating an entity field, or notifying Slack).
* Choose different AI providers and models supported by the Drupal AI Subsystem.

---

## 🧪 Testing and Verification

This module includes a comprehensive test suite of unit and kernel integration tests.

### Running Tests in a DDEV Environment

To run the test suite inside a DDEV-backed Drupal project:

1. **Verify PHPUnit is configured inside DDEV:**
   ```bash
   ddev exec cp core/tests/phpunit.xml.dist core/phpunit.xml
   ```

2. **Execute Unit Tests:**
   ```bash
   ddev exec vendor/bin/phpunit web/modules/custom/ai_fabric/tests/src/Unit/
   ```

3. **Execute Kernel Tests:**
   ```bash
   ddev exec vendor/bin/phpunit -c web/core/phpunit.xml web/modules/custom/ai_fabric/tests/src/Kernel/
   ```
