<?php

declare(strict_types=1);

namespace Drupal\ai_fabric_eca\Plugin\Action;

use Drupal\ai_fabric\Entity\FabricPattern;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\eca\Token\TokenServices;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\ai\Plugin\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executes a Fabric Pattern via the Drupal AI ecosystem within ECA workflows.
 */
#[Action(
  id: 'ai_fabric_eca_run_pattern',
  label: new TranslatableMarkup('Run AI Fabric Pattern'),
  category: new TranslatableMarkup('AI Fabric')
)]
final class FabricEcaAction extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new FabricEcaAction.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AiProviderPluginManager $aiPluginManager,
    private readonly Token $token,
    private readonly TokenServices $ecaTokenServices,
    private readonly LoggerInterface $logger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('ai.provider'),
      $container->get('token'),
      $container->get('eca.token_services'),
      $container->get('logger.factory')->get('ai_fabric_eca')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'pattern_id' => '',
      'user_input' => '',
      'provider_id' => '',
      'model_id' => '',
      'response_token' => 'ai_fabric_response',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $patterns = $this->entityTypeManager->getStorage('fabric_pattern')->loadMultiple();
    $pattern_options = [];
    foreach ($patterns as $pattern) {
      $pattern_options[$pattern->id()] = $pattern->label();
    }

    $form['pattern_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Fabric Pattern'),
      '#options' => $pattern_options,
      '#default_value' => $this->configuration['pattern_id'],
      '#required' => TRUE,
      '#description' => $this->t('Select the Fabric pattern to run.'),
    ];

    $form['user_input'] = [
      '#type' => 'textarea',
      '#title' => $this->t('User Input / Context'),
      '#default_value' => $this->configuration['user_input'],
      '#description' => $this->t('The user input to pass to the pattern. You may use tokens (e.g., [node:body:value]).'),
    ];

    $providers = $this->aiPluginManager->getAvailableProviders();
    $provider_options = [];
    foreach ($providers as $id => $definition) {
      $provider_options[$id] = $definition['label'] ?? $id;
    }

    $form['provider_id'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Provider'),
      '#options' => $provider_options,
      '#default_value' => $this->configuration['provider_id'],
      '#required' => TRUE,
    ];

    $form['model_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Model ID'),
      '#default_value' => $this->configuration['model_id'],
      '#required' => TRUE,
      '#description' => $this->t('The model identifier (e.g., gpt-4, claude-3-opus).'),
    ];

    $form['response_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Response Token Name'),
      '#default_value' => $this->configuration['response_token'],
      '#required' => TRUE,
      '#description' => $this->t('The name of the ECA token to store the LLM response.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['pattern_id'] = $form_state->getValue('pattern_id');
    $this->configuration['user_input'] = $form_state->getValue('user_input');
    $this->configuration['provider_id'] = $form_state->getValue('provider_id');
    $this->configuration['model_id'] = $form_state->getValue('model_id');
    $this->configuration['response_token'] = $form_state->getValue('response_token');
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    $pattern_id = $this->configuration['pattern_id'];
    if (empty($pattern_id)) {
      $this->logger->warning('Fabric ECA Action executed without a selected pattern.');
      return;
    }

    $pattern = $this->entityTypeManager->getStorage('fabric_pattern')->load($pattern_id);
    if (!$pattern instanceof FabricPattern) {
      $this->logger->warning('Fabric pattern @id could not be loaded.', ['@id' => $pattern_id]);
      return;
    }

    $system_prompt = $pattern->getSystemPrompt();
    $user_input = $this->configuration['user_input'];

    // Replace tokens using ECA token services.
    $system_prompt_replaced = $this->ecaTokenServices->replace($system_prompt);
    $user_input_replaced = $this->ecaTokenServices->replace($user_input);

    try {
      /** @var \Drupal\ai\Plugin\ProviderProxy $provider */
      $provider = $this->aiPluginManager->createInstance($this->configuration['provider_id']);

      $chat_input = new ChatInput([
        new ChatMessage('system', $system_prompt_replaced),
        new ChatMessage('user', $user_input_replaced),
      ]);

      $response = $provider->chat($chat_input, $this->configuration['model_id'])->getNormalized();
      $text_response = $response->getText();

      $token_name = $this->configuration['response_token'];
      $this->ecaTokenServices->addTokenData($token_name, $text_response);

    } catch (\Exception $e) {
      $this->logger->error('Error executing Fabric Pattern via AI: @message', ['@message' => $e->getMessage()]);
    }
  }

}
