<?php

declare(strict_types=1);

namespace Drupal\ai_fabric\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Fabric Pattern configuration entity.
 *
 * @ConfigEntityType(
 *   id = "fabric_pattern",
 *   label = @Translation("Fabric Pattern"),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_fabric\FabricPatternListBuilder",
 *   },
 *   config_prefix = "fabric_pattern",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "system_prompt",
 *     "description",
 *     "system_prompt_hash",
 *     "is_customized",
 *   }
 * )
 */
final class FabricPattern extends ConfigEntityBase {

  /**
   * The machine name of the Fabric Pattern.
   */
  protected string $id;

  /**
   * The human-readable label of the Fabric Pattern.
   */
  protected string $label;

  /**
   * The core LLM system prompt.
   */
  protected string $system_prompt = '';

  /**
   * The optional description of what the pattern does.
   */
  protected string $description = '';

  /**
   * The SHA-256 hash of the filesystem prompt when last synchronized.
   */
  protected string $system_prompt_hash = '';

  /**
   * Flag indicating if the prompt has been customized locally in Drupal.
   */
  protected bool $is_customized = false;

  /**
   * Gets the system prompt.
   */
  public function getSystemPrompt(): string {
    return $this->system_prompt;
  }

  /**
   * Sets the system prompt.
   */
  public function setSystemPrompt(string $prompt): self {
    $this->system_prompt = $prompt;
    return $this;
  }

  /**
   * Gets the pattern description.
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * Sets the pattern description.
   */
  public function setDescription(string $description): self {
    $this->description = $description;
    return $this;
  }

  /**
   * Gets the system prompt hash.
   */
  public function getSystemPromptHash(): string {
    return $this->system_prompt_hash;
  }

  /**
   * Sets the system prompt hash.
   */
  public function setSystemPromptHash(string $hash): self {
    $this->system_prompt_hash = $hash;
    return $this;
  }

  /**
   * Checks if the pattern has been customized in Drupal.
   */
  public function isCustomized(): bool {
    return $this->is_customized;
  }

  /**
   * Sets whether the pattern has been customized in Drupal.
   */
  public function setCustomized(bool $customized): self {
    $this->is_customized = $customized;
    return $this;
  }

}
