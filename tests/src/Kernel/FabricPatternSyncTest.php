<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_fabric\Kernel;

use Drupal\ai_fabric\Entity\FabricPattern;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Fabric Pattern configuration entities.
 *
 * @group ai_fabric
 */
final class FabricPatternSyncTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'ai_fabric',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install schema and configuration templates.
    $this->installConfig(['ai_fabric']);
  }

  /**
   * Tests the synchronization and conflict resolution logic.
   */
  public function testSyncAndConflictResolution(): void {
    $temp_dir = $this->createTemporaryDirectoryStructure([
      'extract_wisdom' => 'Extract key wisdom and findings from text.',
      'summarize' => 'Summarize the input text.',
    ]);

    /** @var \Drupal\ai_fabric\FabricSyncService $sync_service */
    $sync_service = $this->container->get('ai_fabric.sync');
    $storage = $this->container->get('entity_type.manager')->getStorage('fabric_pattern');

    // 1. Initial Sync.
    $results = $sync_service->syncPatterns($temp_dir);
    $this->assertEquals(2, $results['created']);
    $this->assertEquals(0, $results['updated']);
    $this->assertEquals(0, $results['skipped']);

    // Verify entities are in the database.
    /** @var \Drupal\ai_fabric\Entity\FabricPattern|null $wisdom */
    $wisdom = $storage->load('extract_wisdom');
    $this->assertNotNull($wisdom);
    $this->assertEquals('Extract Wisdom', $wisdom->label());
    $this->assertEquals('Extract key wisdom and findings from text.', $wisdom->getSystemPrompt());
    $this->assertFalse($wisdom->isCustomized());
    $this->assertEquals(hash('sha256', 'Extract key wisdom and findings from text.'), $wisdom->getSystemPromptHash());

    /** @var \Drupal\ai_fabric\Entity\FabricPattern|null $summarize */
    $summarize = $storage->load('summarize');
    $this->assertNotNull($summarize);
    $this->assertFalse($summarize->isCustomized());

    // 2. Second sync without changes.
    $results = $sync_service->syncPatterns($temp_dir);
    $this->assertEquals(0, $results['created']);
    $this->assertEquals(0, $results['updated']);
    $this->assertEquals(2, $results['skipped']);

    // 3. Update file on disk and sync again.
    $wisdom_file = $temp_dir . '/patterns/extract_wisdom/system.md';
    file_put_contents($wisdom_file, 'Extract key wisdom only.');
    
    $results = $sync_service->syncPatterns($temp_dir);
    $this->assertEquals(0, $results['created']);
    $this->assertEquals(1, $results['updated']);
    $this->assertEquals(1, $results['skipped']);

    $wisdom = $storage->load('extract_wisdom');
    $this->assertEquals('Extract key wisdom only.', $wisdom->getSystemPrompt());
    $this->assertEquals(hash('sha256', 'Extract key wisdom only.'), $wisdom->getSystemPromptHash());

    // 4. Customize entity locally in Drupal and sync.
    $wisdom->setCustomized(TRUE);
    $wisdom->setSystemPrompt('Customized in Drupal UI.');
    $wisdom->save();

    // Sync again: Should skip because it's customized.
    $results = $sync_service->syncPatterns($temp_dir);
    $this->assertEquals(0, $results['created']);
    $this->assertEquals(0, $results['updated']);
    $this->assertEquals(2, $results['skipped']);

    // Verify DB prompt is still our customized one.
    $wisdom = $storage->load('extract_wisdom');
    $this->assertEquals('Customized in Drupal UI.', $wisdom->getSystemPrompt());

    // 5. Sync with force_overwrite = TRUE.
    $results = $sync_service->syncPatterns($temp_dir, TRUE);
    $this->assertEquals(0, $results['created']);
    $this->assertEquals(1, $results['updated']); // wisdom is updated
    $this->assertEquals(1, $results['skipped']); // summarize is unchanged

    $wisdom = $storage->load('extract_wisdom');
    $this->assertEquals('Extract key wisdom only.', $wisdom->getSystemPrompt());
    $this->assertFalse($wisdom->isCustomized());

    $this->cleanupDirectory($temp_dir);
  }

  /**
   * Tests exporting new or customized patterns back to the filesystem.
   */
  public function testExportPatterns(): void {
    $temp_dir = $this->createTemporaryDirectoryStructure([
      'extract_wisdom' => 'Extract key wisdom and findings from text.',
    ]);

    /** @var \Drupal\ai_fabric\FabricSyncService $sync_service */
    $sync_service = $this->container->get('ai_fabric.sync');
    $storage = $this->container->get('entity_type.manager')->getStorage('fabric_pattern');

    // Run initial sync to set baseline.
    $sync_service->syncPatterns($temp_dir);

    // 1. Create a brand new pattern locally in Drupal (Local Only).
    /** @var \Drupal\ai_fabric\Entity\FabricPattern $new_pattern */
    $new_pattern = $storage->create([
      'id' => 'explain_code',
      'label' => 'Explain Code',
      'system_prompt' => 'Explain the following code snippet.',
      'description' => 'Explains source code.',
      'system_prompt_hash' => '',
      'is_customized' => FALSE,
    ]);
    $new_pattern->save();

    // 2. Modify the existing pattern in Drupal (Customized Locally).
    /** @var \Drupal\ai_fabric\Entity\FabricPattern $wisdom */
    $wisdom = $storage->load('extract_wisdom');
    $wisdom->setCustomized(TRUE);
    $wisdom->setSystemPrompt('Extract wisdom with high precision.');
    $wisdom->save();

    // Run export.
    $exported = $sync_service->exportPatterns($temp_dir);
    $this->assertCount(2, $exported);
    $this->assertContains('explain_code', $exported);
    $this->assertContains('extract_wisdom', $exported);

    // Assert files are written correctly.
    $wisdom_file = $temp_dir . '/patterns/extract_wisdom/system.md';
    $new_file = $temp_dir . '/patterns/explain_code/system.md';

    $this->assertFileExists($wisdom_file);
    $this->assertFileExists($new_file);

    $this->assertEquals('Extract wisdom with high precision.', file_get_contents($wisdom_file));
    $this->assertEquals('Explain the following code snippet.', file_get_contents($new_file));

    // Assert DB state is marked as synced (is_customized = FALSE).
    $wisdom = $storage->load('extract_wisdom');
    $this->assertFalse($wisdom->isCustomized());
    $this->assertEquals(hash('sha256', 'Extract wisdom with high precision.'), $wisdom->getSystemPromptHash());

    $new = $storage->load('explain_code');
    $this->assertFalse($new->isCustomized());
    $this->assertEquals(hash('sha256', 'Explain the following code snippet.'), $new->getSystemPromptHash());

    $this->cleanupDirectory($temp_dir);
  }

  /**
   * Helper to create a temporary patterns directory structure.
   */
  private function createTemporaryDirectoryStructure(array $patterns): string {
    $temp_dir = $this->container->get('file_system')->realpath('public://test_fabric_' . uniqid());
    mkdir($temp_dir . '/patterns', 0777, TRUE);

    foreach ($patterns as $name => $prompt) {
      mkdir($temp_dir . '/patterns/' . $name, 0777, TRUE);
      file_put_contents($temp_dir . '/patterns/' . $name . '/system.md', $prompt);
    }

    return $temp_dir;
  }

  /**
   * Helper to clean up a directory recursively.
   */
  private function cleanupDirectory(string $dir): void {
    if (!is_dir($dir)) {
      return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
      (is_dir("$dir/$file")) ? $this->cleanupDirectory("$dir/$file") : unlink("$dir/$file");
    }
    rmdir($dir);
  }

}

