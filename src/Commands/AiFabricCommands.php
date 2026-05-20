<?php

declare(strict_types=1);

namespace Drupal\ai_fabric\Commands;

use Drupal\ai_fabric\FabricSyncService;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush command file for the ai_fabric module.
 */
final class AiFabricCommands extends DrushCommands {
  use StringTranslationTrait;

  /**
   * Constructs a new AiFabricCommands instance.
   */
  public function __construct(
    private readonly FabricSyncService $syncService,
  ) {
    parent::__construct();
  }

  /**
   * Synchronizes Fabric patterns from the filesystem into Drupal.
   *
   * @param string $path
   *   The filesystem path to the cloned Fabric directory.
   * @param array $options
   *   An array of options.
   *
   * @option force Force overwrite of locally customized prompts in Drupal.
   * @usage drush ai-fabric:sync /path/to/fabric
   *   Syncs all patterns.
   * @usage drush ai-fabric:sync /path/to/fabric --force
   *   Syncs and overwrites local modifications.
   *
   * @command ai-fabric:sync
   * @aliases afs
   */
  public function sync(string $path, array $options = ['force' => FALSE]): void {
    $force = (bool) $options['force'];

    $this->io()->title($this->t('Starting Fabric patterns synchronization...'));
    $this->io()->text($this->t('Reading patterns from: @path', ['@path' => $path]));

    try {
      $results = $this->syncService->syncPatterns($path, $force);

      $this->io()->newLine();
      $this->io()->success($this->t('Synchronization completed successfully!'));
      
      $this->io()->table(
        [$this->t('Action'), $this->t('Count')],
        [
          [$this->t('Created'), $results['created']],
          [$this->t('Updated'), $results['updated']],
          [$this->t('Skipped/Unchanged'), $results['skipped']],
        ]
      );
    }
    catch (\Exception $e) {
      $this->io()->error($this->t('Sync failed: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Exports customized or new patterns from Drupal back to the local directory.
   *
   * @param string $path
   *   The filesystem path to the cloned Fabric directory.
   *
   * @usage drush ai-fabric:contrib /path/to/fabric
   *   Exports new and customized prompts back to Fabric repository.
   *
   * @command ai-fabric:contrib
   * @aliases afc
   */
  public function contrib(string $path): void {
    $this->io()->title($this->t('Starting Fabric patterns export (Contrib Back)...'));
    $this->io()->text($this->t('Exporting changes to: @path', ['@path' => $path]));

    try {
      $exported = $this->syncService->exportPatterns($path);

      if (empty($exported)) {
        $this->io()->success($this->t('No modified or new patterns found to export. Upstream is already in sync.'));
        return;
      }

      $this->io()->newLine();
      $this->io()->success($this->t('Successfully exported @count pattern(s)!', ['@count' => count($exported)]));
      
      $this->io()->listing(array_map(fn($item) => $this->t('Exported: @name', ['@name' => $item]), $exported));

      // Git helper assistant.
      $gitDir = realpath($path) . '/.git';
      if (is_dir($gitDir)) {
        $this->io()->section($this->t('Git Helper Advice'));
        $this->io()->text($this->t('A .git repository was detected in your target path.'));
        $this->io()->text($this->t('You can review, commit, and push your changes to Fabric upstream by running:'));
        $this->io()->newLine();
        $this->io()->block(
          [
            "cd " . realpath($path),
            "git status",
            "git diff",
            "git checkout -b contrib/my-new-patterns",
            "git add .",
            "git commit -m \"feat: add custom/modified fabric patterns from Drupal\"",
          ],
          NULL,
          'fg=cyan'
        );
      }
    }
    catch (\Exception $e) {
      $this->io()->error($this->t('Export failed: @error', ['@error' => $e->getMessage()]));
    }
  }

}
