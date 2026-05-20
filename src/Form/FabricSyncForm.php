<?php

declare(strict_types=1);

namespace Drupal\ai_fabric\Form;

use Drupal\ai_fabric\FabricSyncService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an administrative synchronization form for AI Fabric patterns.
 */
final class FabricSyncForm extends FormBase {

  /**
   * Constructs a new FabricSyncForm.
   *
   * @param \Drupal\ai_fabric\FabricSyncService $syncService
   *   The Fabric synchronization service.
   */
  public function __construct(
    protected FabricSyncService $syncService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('ai_fabric.sync')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_fabric_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['local_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Fabric Patterns Path'),
      '#description' => $this->t('The absolute path to the directory containing Fabric patterns (e.g., /home/user/fabric). It should contain a "patterns" subdirectory.'),
      '#required' => TRUE,
      '#default_value' => '',
      '#maxlength' => 512,
    ];

    $form['force_overwrite'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force overwrite of locally customized prompts'),
      '#description' => $this->t('If checked, local changes made to system prompts in Drupal will be overwritten by the version from the filesystem.'),
      '#default_value' => FALSE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import from Path'),
      '#name' => 'import',
      '#button_type' => 'primary',
    ];

    $form['actions']['export'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export to Path'),
      '#name' => 'export',
      '#button_type' => 'secondary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $path = trim($form_state->getValue('local_path'));

    if (empty($path)) {
      $form_state->setErrorByName('local_path', $this->t('The Fabric patterns path cannot be empty.'));
      return;
    }

    // Security Gate: Ensure it is a valid directory.
    if (!is_dir($path)) {
      $form_state->setErrorByName('local_path', $this->t('The path "@path" is not a valid directory or is not readable.', ['@path' => $path]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $path = trim($form_state->getValue('local_path'));
    $force = (bool) $form_state->getValue('force_overwrite');

    $triggering_element = $form_state->getTriggeringElement();
    $action = $triggering_element ? end($triggering_element['#parents']) : 'import';

    try {
      if ($action === 'export') {
        $exported = $this->syncService->exportPatterns($path);
        $this->messenger()->addStatus($this->t('Export complete: @count pattern(s) successfully written back to the filesystem.', [
          '@count' => count($exported),
        ]));
        if (!empty($exported)) {
          $this->messenger()->addWarning($this->t('Exported patterns: @list. Inspect changes via git diff in your patterns folder.', [
            '@list' => implode(', ', $exported),
          ]));
        }
      }
      else {
        // Default action is import.
        $results = $this->syncService->syncPatterns($path, $force);
        $this->messenger()->addStatus($this->t('Synchronization complete: @created patterns created, @updated updated, @skipped skipped.', [
          '@created' => $results['created'],
          '@updated' => $results['updated'],
          '@skipped' => $results['skipped'],
        ]));
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred during execution: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

}
