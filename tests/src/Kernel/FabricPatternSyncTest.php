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
   * Tests basic entity creation, getters, and setters.
   */
  public function testEntityOperations(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('fabric_pattern');

    // Create a pattern.
    /** @var \Drupal\ai_fabric\Entity\FabricPattern $pattern */
    $pattern = $storage->create([
      'id' => 'extract_wisdom',
      'label' => 'Extract Wisdom',
      'system_prompt' => 'Extract key wisdom and findings from text.',
      'description' => 'Extracts deep insights.',
      'system_prompt_hash' => 'dummy_hash',
      'is_customized' => false,
    ]);

    $pattern->save();

    // Reload and assert properties.
    /** @var \Drupal\ai_fabric\Entity\FabricPattern|null $loaded */
    $loaded = $storage->load('extract_wisdom');
    $this->assertNotNull($loaded);
    $this->assertEquals('Extract Wisdom', $loaded->label());
    $this->assertEquals('Extract key wisdom and findings from text.', $loaded->getSystemPrompt());
    $this->assertEquals('Extracts deep insights.', $loaded->getDescription());
    $this->assertEquals('dummy_hash', $loaded->getSystemPromptHash());
    $this->assertFalse($loaded->isCustomized());

    // Update customization.
    $loaded->setCustomized(true);
    $loaded->setSystemPrompt('Overridden custom prompt');
    $loaded->save();

    // Reload again and assert updated properties.
    /** @var \Drupal\ai_fabric\Entity\FabricPattern|null $reloaded */
    $reloaded = $storage->load('extract_wisdom');
    $this->assertTrue($reloaded->isCustomized());
    $this->assertEquals('Overridden custom prompt', $reloaded->getSystemPrompt());
  }

}
