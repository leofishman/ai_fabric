<?php

declare(strict_types=1);

namespace Drupal\ai_fabric;

use Drupal\ai_fabric\Entity\FabricPattern;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Handles synchronization between local Fabric patterns and Drupal config.
 */
final class FabricSyncService {

  /**
   * The configuration entity storage for FabricPattern.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $patternStorage;

  /**
   * The logger channel.
   */
  private LoggerChannelInterface $logger;

  /**
   * Constructs a new FabricSyncService.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->patternStorage = $this->entityTypeManager->getStorage('fabric_pattern');
    $this->logger = $loggerFactory->get('ai_fabric');
  }

  /**
   * Syncs patterns from a local directory into Drupal.
   *
   * @param string $local_path
   *   The filesystem path to the Fabric directory.
   * @param bool $force_overwrite
   *   Whether to overwrite locally customized prompts in Drupal.
   *
   * @return array
   *   An array containing counts of 'created', 'updated', and 'skipped'.
   */
  public function syncPatterns(string $local_path, bool $force_overwrite = FALSE): array {
    $results = ['created' => 0, 'updated' => 0, 'skipped' => 0];

    // Sanitize and resolve real path to guard against directory traversal.
    $resolved_path = str_contains($local_path, '://') ? $local_path : realpath($local_path);
    if ($resolved_path === FALSE || !is_dir($resolved_path)) {
      $this->logger->error('Invalid patterns path specified: @path', ['@path' => $local_path]);
      throw new \InvalidArgumentException(sprintf('The path "%s" is not a valid directory.', $local_path));
    }

    // Determine the actual patterns folder (check for a /patterns subfolder first).
    $patterns_dir = $resolved_path;
    if (is_dir($resolved_path . '/patterns')) {
      $patterns_dir = $resolved_path . '/patterns';
    }

    if (!is_dir($patterns_dir)) {
      return $results;
    }

    $files = scandir($patterns_dir);
    if ($files === FALSE) {
      return $results;
    }

    foreach ($files as $file) {
      if ($file === '.' || $file === '..') {
        continue;
      }
      $dir = $patterns_dir . '/' . $file;
      if (!is_dir($dir)) {
        continue;
      }

      $system_file = $dir . '/system.md';
      if (!file_exists($system_file)) {
        continue;
      }

      $machine_name = $this->sanitizeMachineName(basename($dir));
      $prompt_content = file_get_contents($system_file);
      if ($prompt_content === FALSE) {
        $this->logger->warning('Failed to read file: @file', ['@file' => $system_file]);
        continue;
      }

      $prompt_content = trim($prompt_content);
      $file_hash = hash('sha256', $prompt_content);

      /** @var \Drupal\ai_fabric\Entity\FabricPattern|null $existing_pattern */
      $existing_pattern = $this->patternStorage->load($machine_name);

      if ($existing_pattern) {
        // Handle sync conflict: protect local customizations.
        if ($existing_pattern->isCustomized() && !$force_overwrite) {
          $this->logger->info('Pattern @id skipped during sync due to local customizations.', ['@id' => $machine_name]);
          $results['skipped']++;
          continue;
        }

        // Check if actual file content has changed compared to last sync, or if forced to overwrite customizations.
        $needs_update = ($existing_pattern->getSystemPromptHash() !== $file_hash) || ($existing_pattern->isCustomized() && $force_overwrite);

        if ($needs_update) {
          $existing_pattern->setSystemPrompt($prompt_content);
          $existing_pattern->setSystemPromptHash($file_hash);
          if ($force_overwrite) {
            $existing_pattern->setCustomized(FALSE);
          }
          $existing_pattern->save();
          $results['updated']++;
        }
        else {
          $results['skipped']++;
        }
      }
      else {
        // Create new FabricPattern.
        $new_pattern = $this->patternStorage->create([
          'id' => $machine_name,
          'label' => ucwords(str_replace('_', ' ', $machine_name)),
          'system_prompt' => $prompt_content,
          'system_prompt_hash' => $file_hash,
          'is_customized' => FALSE,
        ]);
        $new_pattern->save();
        $results['created']++;
      }
    }

    return $results;
  }

  /**
   * Exports customized or new patterns from Drupal back to the local directory.
   *
   * @param string $local_path
   *   The filesystem path to the Fabric directory.
   *
   * @return array
   *   An array of pattern machine names that were successfully exported.
   */
  public function exportPatterns(string $local_path): array {
    $exported = [];

    $resolved_path = str_contains($local_path, '://') ? $local_path : realpath($local_path);
    if ($resolved_path === FALSE || !is_dir($resolved_path)) {
      $this->logger->error('Invalid patterns export path: @path', ['@path' => $local_path]);
      throw new \InvalidArgumentException(sprintf('The export path "%s" is not a valid directory.', $local_path));
    }

    // Determine target patterns directory.
    $patterns_dir = $resolved_path;
    if (is_dir($resolved_path . '/patterns')) {
      $patterns_dir = $resolved_path . '/patterns';
    }

    // Load all patterns.
    $patterns = $this->patternStorage->loadMultiple();

    foreach ($patterns as $pattern) {
      /** @var \Drupal\ai_fabric\Entity\FabricPattern $pattern */
      
      // Export if customized locally OR if it is brand new (no original hash).
      if ($pattern->isCustomized() || empty($pattern->getSystemPromptHash())) {
        $machine_name = $pattern->id();
        $target_folder = $patterns_dir . '/' . $machine_name;

        // Ensure target folder exists securely.
        if (!is_dir($target_folder)) {
          $this->fileSystem->prepareDirectory($target_folder, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        }

        $system_file = $target_folder . '/system.md';
        $content = $pattern->getSystemPrompt();

        if (file_put_contents($system_file, $content) !== FALSE) {
          $new_hash = hash('sha256', $content);
          
          // Re-establish sync state.
          $pattern->setCustomized(FALSE);
          $pattern->setSystemPromptHash($new_hash);
          $pattern->save();

          $exported[] = $machine_name;
        }
        else {
          $this->logger->error('Failed to write exported pattern file: @file', ['@file' => $system_file]);
        }
      }
    }

    return $exported;
  }

  /**
   * Sanitizes folder name into a valid Drupal config machine name.
   */
  private function sanitizeMachineName(string $name): string {
    $name = strtolower($name);
    $name = str_replace('-', '_', $name);
    return preg_replace('/[^a-z0-9_]/', '', $name) ?? $name;
  }

}
