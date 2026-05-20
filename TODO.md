# TODO: AI Fabric Integration Module for Drupal 10/11

This document outlines the step-by-step implementation plan for creating the **AI Fabric Integration** (`ai_fabric`) module. This module integrates Daniel Miessler's **Fabric** patterns into the Drupal AI subsystem ecosystem by syncing local pattern directories as custom config entities, handling local customization conflicts, and exporting changes back upstream (Contrib Back).

---

## 🛠️ Module Architecture Blueprint

We will create a structured, highly maintainable Drupal 10/11 module using strict typing, constructor property promotion, and clean dependency injection.

```text
ai_fabric/
├── ai_fabric.info.yml
├── config/
│   └── schema/
│       └── ai_fabric.schema.yml             # Strict schema definition for config entities
├── src/
│   ├── Entity/
│   │   └── FabricPattern.php                # Config Entity representing a Fabric pattern
│   ├── FabricPatternListBuilder.php         # Clean admin list builder showing sync status
│   ├── FabricSyncService.php                # Core synchronization, parsing, and export engine
│   └── Commands/
│       └── AiFabricCommands.php             # Drush 11/12 Commands for sync & upstream contrib
└── tests/
    └── src/
        ├── Unit/
        │   └── FabricSyncServiceTest.php    # Isolated unit tests for directory parsing
        └── Kernel/
            ├── FabricPatternSyncTest.php    # Integration tests verifying DB, conflicts, and export
```

---

## 📋 Implementation Tasklist

### Phase 1: Foundation & Schema Definitions
- [x] **Create `ai_fabric.info.yml`**
  - Define name as `Drupal AI Fabric Integration`.
  - Set core compatibility to `^10 || ^11`.
  - Add dependency on `ai:ai`.
- [x] **Create Config Entity Schema `config/schema/ai_fabric.schema.yml`**
  - Map `fabric_pattern.*` properties.
  - Define exact data types for validation:
    - `id`: string (machine name)
    - `label`: label (human-readable title)
    - `system_prompt`: string (the core LLM instruction text)
    - `description`: string (optional pattern description)
    - `system_prompt_hash`: string (SHA-256 hash of filesystem system.md at last sync)
    - `is_customized`: boolean (flag indicating if edited directly in Drupal)

### Phase 2: Configuration Entity
- [x] **Implement `FabricPattern` Entity (`src/Entity/FabricPattern.php`)**
  - Use `#[ConfigEntityType]` attribute or annotations.
  - Define fields (`id`, `label`, `system_prompt`, `description`, `system_prompt_hash`, `is_customized`).
  - Implement getters and setters with strict types.
  - Set `admin_permission` to `administer site configuration`.
- [x] **Implement `FabricPatternListBuilder` (`src/FabricPatternListBuilder.php`)**
  - Extend `ConfigEntityListBuilder`.
  - Render an admin-friendly table displaying pattern names, descriptions, and a status column highlighting:
    - `Synced` (matches file hash, not customized)
    - `Customized Locally` (modified in Drupal UI)
    - `Local Only` (created in Drupal, not yet exported to disk)

### Phase 3: Synchronization & Conflict Resolution Engine
- [x] **Implement `FabricSyncService` (`src/FabricSyncService.php`)**
  - Register as `ai_fabric.sync` service in `ai_fabric.services.yml` with autowiring.
  - Inject dependencies: `EntityTypeManagerInterface`, `FileSystemInterface`, and `LoggerChannelFactoryInterface`.
  - **Security Gate**: Prevent directory traversal by sanitizing input paths with `realpath` and validating path structure.
  - **Conflict Detection Algorithm**:
    - Compute SHA-256 hash of filesystem `system.md`.
    - If entity exists in Drupal:
      - If `is_customized` is `TRUE`, log a conflict warning and skip parsing (protect local UI changes from being overwritten) unless `$force_overwrite` is set to `TRUE`.
      - If `is_customized` is `FALSE` but file hash differs from `system_prompt_hash`, safely update the prompt and update `system_prompt_hash` with the new file hash.
      - If file hash matches `system_prompt_hash`, skip (no changes).
    - If entity does not exist: Create it, compute and set `system_prompt_hash`, and set `is_customized` to `FALSE`.

### Phase 4: Contrib Back (Upstream Export)
- [x] **Implement Upstream Export in `FabricSyncService`**
  - Add `exportPatterns($local_path)` method:
    - Load all entities where `is_customized = TRUE` OR where there is no `system_prompt_hash` (meaning it was newly created in Drupal).
    - For each pattern:
      - Sanitize the machine name to prevent path traversal when writing.
      - Ensure the corresponding folder `/patterns/[pattern-name]/` exists or create it.
      - Write the `system_prompt` back to `system.md` in that directory.
      - Reset `is_customized` to `FALSE` and update `system_prompt_hash` to match the newly written file's SHA-256 hash, re-establishing sync parity.

### Phase 5: Drush Command CLI
- [x] **Implement `AiFabricCommands` (`src/Commands/AiFabricCommands.php`)**
  - Define command `ai-fabric:sync` with `path` argument and `--force` flag:
    - Runs the imports.
    - `--force` forces overwrite of locally customized entities.
  - Define command `ai-fabric:contrib` (or `ai-fabric:export`) with `path` argument:
    - Scans Drupal for new or customized patterns and exports them back to the filesystem path.
    - Displays a clean success summary of files updated.
    - Generates Git-friendly advice if a `.git` folder is detected (e.g., "Changes written to [path]. You can run `git diff` or `git status` inside that folder to inspect and commit.").

### Phase 6: Verification & Quality Assurance (Testing)
- [x] **Create Unit Test Suite (`tests/src/Unit/FabricSyncServiceTest.php`)**
  - Verify prompt parsing logic.
  - Mock `FileSystemInterface` and directory structures.
- [x] **Create Kernel Test Suite (`tests/src/Kernel/FabricPatternSyncTest.php`)**
  - Boot a minimal Drupal system database, enable the `ai_fabric` module, and install config schemas.
  - **Verify Sync & Conflict Resolution**:
    - Run initial sync → Verify patterns are created in DB.
    - Modify a pattern in DB and set `is_customized = TRUE`.
    - Run sync again → Verify the database entity is NOT overwritten (conflict preserved).
    - Run sync with `--force = TRUE` → Verify the customization IS overwritten by the file version.
  - **Verify Contrib Back**:
    - Create a brand new entity in Drupal.
    - Modify an existing entity in Drupal (`is_customized = TRUE`).
    - Run `exportPatterns` pointing to a virtual/mock directory structure.
    - Assert that directories and `system.md` files are correctly written to the filesystem with exact prompt contents.

---

## 🛡️ Coding Standards & Security Guards

All developed code must adhere strictly to these principles, reflecting senior Drupal architectural standards:

1. **Strict Typing**: Every file must start with `declare(strict_types=1);` and all parameters/return types must be fully typed.
2. **Strict Dependency Injection**: Avoid all global utility calls (`\Drupal::*`). Use constructor injection and clean service definitions.
3. **No Raw SQL or Dynamic Commands**: Always use Drupal's built-in query builders or ORM mechanisms to query configuration databases.
4. **Clean Parameter Sanitization**: File paths must be fully validated against local directory bounds prior to opening file descriptors.
5. **English Standard**: All internal codebase comments, variables, and documentation must be written in fluent, precise English.
6. **No Placeholders**: Write fully functional classes with real error-handling and data parsing, completely avoiding stub patterns.

---

## 🚀 Next Steps & Future Phases

### Phase 7: Parallel Collaboration (Preparing the Ground for Jules)
- [x] **Establish Jules' isolated development branch**
  - Create the `jules/eca-integration` (or a generic feature branch) on the GitHub remote.
  - Document this branch so Jules can checkout and work on it without touching `main` directly.
- [x] **Define and document Jules' task specifications**
  - Write down the architectural boundaries for Jules' feature development.
  - Provide a clear API contract of the `ai_fabric.sync` service.
- [ ] **Set up Quality Control Integration (CI Gatekeeper) for Antigravity**
  - Define the verification procedure to download, mount, test, and merge Jules' changes after automatic testing in local DDEV.

### Phase 8: UI Enhancements & Admin Panel
- [x] **Implement Admin Form routes (`ai_fabric.routing.yml`)**
  - Register `/admin/config/services/ai-fabric` settings route.
  - Define custom admin form class for syncing configuration via standard Drupal UI button clicks.
