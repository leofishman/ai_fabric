<?php

declare(strict_types=1);

namespace Drupal\ai_fabric;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Fabric Pattern entities.
 */
final class FabricPatternListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Fabric Pattern');
    $header['id'] = $this->t('Machine Name');
    $header['description'] = $this->t('Description');
    $header['status'] = $this->t('Sync Status');
    $header['char_count'] = $this->t('Prompt Length');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ai_fabric\Entity\FabricPattern $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['description'] = $entity->getDescription();

    // Determine the status representation.
    if ($entity->isCustomized()) {
      $status = $this->t('Customized Locally');
    }
    elseif (empty($entity->getSystemPromptHash())) {
      $status = $this->t('Local Only');
    }
    else {
      $status = $this->t('Synced');
    }

    $row['status'] = $status;
    $row['char_count'] = $this->t('@count chars', [
      '@count' => strlen($entity->getSystemPrompt()),
    ]);

    return $row + parent::buildRow($entity);
  }

}
