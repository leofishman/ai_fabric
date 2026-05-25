<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_fabric\Kernel;

use Drupal\ai_fabric\Form\FabricSyncForm;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Fabric Pattern synchronization admin form.
 *
 * @group ai_fabric
 */
#[RunTestsInSeparateProcesses]
final class FabricPatternAdminFormTest extends KernelTestBase {

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
    $this->installConfig(['ai_fabric']);
  }

  /**
   * Tests that the form has the expected structure and elements.
   */
  public function testFormStructure(): void {
    $form_builder = $this->container->get('form_builder');
    $form = $form_builder->getForm(FabricSyncForm::class);

    $this->assertArrayHasKey('local_path', $form);
    $this->assertEquals('textfield', $form['local_path']['#type']);
    $this->assertArrayHasKey('force_overwrite', $form);
    $this->assertEquals('checkbox', $form['force_overwrite']['#type']);
    
    $this->assertArrayHasKey('actions', $form);
    $this->assertArrayHasKey('import', $form['actions']);
    $this->assertEquals('submit', $form['actions']['import']['#type']);
    $this->assertArrayHasKey('export', $form['actions']);
    $this->assertEquals('submit', $form['actions']['export']['#type']);
  }

  /**
   * Tests programmatically submitting the import action.
   */
  public function testImportSubmission(): void {
    $temp_dir = $this->createTemporaryDirectoryStructure([
      'test_wisdom' => 'Deep wisdom prompt.',
    ]);

    $form_state = new FormState();
    $form_state->setValues([
      'local_path' => $temp_dir,
      'force_overwrite' => NULL,
    ]);
    
    // Set the triggering element to simulate clicking the import button.
    $form_state->setTriggeringElement([
      '#parents' => ['import'],
      '#name' => 'import',
      '#value' => 'import',
    ]);

    $form_builder = $this->container->get('form_builder');
    $form_builder->submitForm(FabricSyncForm::class, $form_state);

    // Verify there are no form errors.
    $this->assertEmpty($form_state->getErrors());

    // Verify that the configuration was saved correctly.
    $config = $this->container->get('config.factory')->get('ai_fabric.settings');
    $this->assertEquals($temp_dir, $config->get('local_path'));
    $this->assertFalse($config->get('force_overwrite'));

    // Assert that the entity was created successfully by the sync service.
    $storage = $this->container->get('entity_type.manager')->getStorage('fabric_pattern');
    $pattern = $storage->load('test_wisdom');
    $this->assertNotNull($pattern);
    $this->assertEquals('Deep wisdom prompt.', $pattern->getSystemPrompt());

    $this->cleanupDirectory($temp_dir);
  }

  /**
   * Tests programmatically submitting the export action.
   */
  public function testExportSubmission(): void {
    $temp_dir = $this->createTemporaryDirectoryStructure([]);
    
    // Create an entity in the database that is marked as customized to trigger export.
    $storage = $this->container->get('entity_type.manager')->getStorage('fabric_pattern');
    $pattern = $storage->create([
      'id' => 'custom_wisdom',
      'label' => 'Custom Wisdom',
      'system_prompt' => 'Highly precise custom instructions.',
      'system_prompt_hash' => '',
      'is_customized' => TRUE,
    ]);
    $pattern->save();

    $form_state = new FormState();
    $form_state->setValues([
      'local_path' => $temp_dir,
      'force_overwrite' => NULL,
    ]);
    
    // Set the triggering element to simulate clicking the export button.
    $form_state->setTriggeringElement([
      '#parents' => ['export'],
      '#name' => 'export',
      '#value' => 'export',
    ]);

    $form_builder = $this->container->get('form_builder');
    $form_builder->submitForm(FabricSyncForm::class, $form_state);

    $this->assertEmpty($form_state->getErrors());

    // Verify that the configuration was saved correctly.
    $config = $this->container->get('config.factory')->get('ai_fabric.settings');
    $this->assertEquals($temp_dir, $config->get('local_path'));
    $this->assertFalse($config->get('force_overwrite'));

    // Assert the file was successfully written.
    $expected_file = $temp_dir . '/patterns/custom_wisdom/system.md';
    $this->assertFileExists($expected_file);
    $this->assertEquals('Highly precise custom instructions.', file_get_contents($expected_file));

    $this->cleanupDirectory($temp_dir);
  }

  /**
   * Tests that default values are correctly loaded from config.
   */
  public function testFormDefaultValues(): void {
    // Set some initial configuration values.
    $this->container->get('config.factory')->getEditable('ai_fabric.settings')
      ->set('local_path', '/path/to/some/fabric')
      ->set('force_overwrite', TRUE)
      ->save();

    $form_builder = $this->container->get('form_builder');
    $form = $form_builder->getForm(FabricSyncForm::class);

    $this->assertEquals('/path/to/some/fabric', $form['local_path']['#default_value']);
    $this->assertTrue($form['force_overwrite']['#default_value']);
  }


  /**
   * Helper to create a temporary directory.
   */
  private function createTemporaryDirectoryStructure(array $patterns): string {
    $temp_dir = $this->container->get('file_system')->realpath('public://test_form_fabric_' . uniqid());
    mkdir($temp_dir . '/patterns', 0777, TRUE);

    foreach ($patterns as $name => $prompt) {
      mkdir($temp_dir . '/patterns/' . $name, 0777, TRUE);
      file_put_contents($temp_dir . '/patterns/' . $name . '/system.md', $prompt);
    }

    return $temp_dir;
  }

  /**
   * Helper to clean up a directory.
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
