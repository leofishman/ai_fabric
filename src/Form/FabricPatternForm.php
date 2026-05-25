<?php

declare(strict_types=1);

namespace Drupal\ai_fabric\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for the Fabric Pattern config entity.
 */
final class FabricPatternForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\ai_fabric\Entity\FabricPattern $pattern */
    $pattern = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $pattern->label(),
      '#description' => $this->t('Label for the Fabric Pattern.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $pattern->id(),
      '#machine_name' => [
        'exists' => '\Drupal\ai_fabric\Entity\FabricPattern::load',
      ],
      '#disabled' => !$pattern->isNew(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $pattern->getDescription(),
      '#description' => $this->t('A brief description of this pattern.'),
    ];

    $form['system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System Prompt'),
      '#default_value' => $pattern->getSystemPrompt(),
      '#description' => $this->t('The system prompt text for this pattern.'),
      '#rows' => 15,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\ai_fabric\Entity\FabricPattern $pattern */
    $pattern = $this->entity;

    // Determine if the prompt was actually changed.
    $original_prompt = '';
    if (!$pattern->isNew()) {
      $original = $this->entityTypeManager->getStorage('fabric_pattern')->loadUnchanged($pattern->id());
      if ($original instanceof \Drupal\ai_fabric\Entity\FabricPattern) {
        $original_prompt = $original->getSystemPrompt();
      }
    }

    $new_prompt = $pattern->getSystemPrompt();

    // If the prompt was changed in the UI and it's not a new entity,
    // mark it as customized locally so sync doesn't overwrite it.
    if (!$pattern->isNew() && $original_prompt !== $new_prompt) {
      $pattern->setCustomized(TRUE);
    }

    $status = $pattern->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Created the %label Fabric Pattern.', [
        '%label' => $pattern->label(),
      ]));
    }
    else {
      $this->messenger()->addStatus($this->t('Saved the %label Fabric Pattern.', [
        '%label' => $pattern->label(),
      ]));
    }

    $form_state->setRedirectUrl($pattern->toUrl('collection'));

    return $status;
  }

}
