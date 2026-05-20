<?php

declare(strict_types=1);

namespace Drupal\ai_fabric\Plugin\Action;

use Drupal\ai\Plugin\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Token;
use Drupal\eca\Token\TokenServices;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Executes a Fabric Pattern via the Drupal AI ecosystem within ECA workflows.
 *
 * @Action(
 *   id = "ai_fabric_eca_run_pattern",
 *   label = @Translation("Run AI Fabric Pattern"),
 *   category = @Translation("AI Fabric")
 * )
 */
final class FabricEcaAction extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\Plugin\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * The core token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The ECA token services.
   *
   * @var \Drupal\eca\Token\TokenServices
   */
  protected TokenServices $ecaTokenServices;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a new FabricEcaAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\ai\Plugin\AiProviderPluginManager $ai_provider_manager
   *   The AI provider plugin manager.
   * @param \Drupal\Core\Utility\Token $token
   *   The core token service.
   * @param \Drupal\eca\Token\TokenServices $eca_token_services
   *   The ECA token services.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    AiProviderPluginManager $ai_provider_manager,
    Token $token,
    TokenServices $eca_token_services,
    LoggerInterface $logger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->aiProviderManager = $ai_provider_manager;
    $this->token = $token;
    $this->ecaTokenServices = $eca_token_services;
    $this->logger = $logger;
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
      $container->get('logger.factory')->get('ai_fabric')
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
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
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
      '#description' => $this->t('Select the Fabric Pattern to run.'),
    ];

    $form['user_input'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Context/User Input'),
      '#default_value' => $this->configuration['user_input'],
      '#required' => TRUE,
      '#description' => $this->t('The input prompt. You can use standard Drupal tokens (e.g. [node:body:value]).'),
    ];

    $provider_options = [];
    $providers = $this->aiProviderManager->getDefinitions();
    foreach ($providers as $id => $definition) {
      $provider_options[$id] = $definition['label'];
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
      '#title' => $this->t('Model'),
      '#default_value' => $this->configuration['model_id'],
      '#required' => TRUE,
      '#description' => $this->t('The model ID to use (e.g. gpt-4o, claude-3-opus).'),
    ];

    $form['response_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Response Token Name'),
      '#default_value' => $this->configuration['response_token'],
      '#required' => TRUE,
      '#description' => $this->t('The ECA token name where the LLM response will be saved.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['pattern_id'] = $form_state->getValue('pattern_id');
    $this->configuration['user_input'] = $form_state->getValue('user_input');
    $this->configuration['provider_id'] = $form_state->getValue('provider_id');
    $this->configuration['model_id'] = $form_state->getValue('model_id');
    $this->configuration['response_token'] = $form_state->getValue('response_token');
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL): void {
    $pattern_id = $this->configuration['pattern_id'];
    $user_input = $this->configuration['user_input'];
    $provider_id = $this->configuration['provider_id'];
    $model_id = $this->configuration['model_id'];
    $response_token = $this->configuration['response_token'];

    if (empty($pattern_id) || empty($provider_id) || empty($model_id) || empty($response_token)) {
      $this->logger->warning('Fabric pattern action executed with missing configuration.');
      return;
    }

    /** @var \Drupal\ai_fabric\Entity\FabricPattern|null $pattern */
    $pattern = $this->entityTypeManager->getStorage('fabric_pattern')->load($pattern_id);

    if (!$pattern) {
      $this->logger->warning('Fabric pattern action executed with invalid pattern ID: @id', ['@id' => $pattern_id]);
      return;
    }

    $system_prompt = $pattern->getSystemPrompt();

    // Replaces tokens using ECA services if available.
    if ($this->ecaTokenServices) {
      $system_prompt = $this->ecaTokenServices->replace($system_prompt);
      $user_input = $this->ecaTokenServices->replace($user_input);
    } else {
      // Fallback to standard token service if ECA token service is unavailable.
      $system_prompt = $this->token->replace($system_prompt);
      $user_input = $this->token->replace($user_input);
    }

    try {
      /** @var \Drupal\ai\Plugin\ProviderProxy $provider */
      $provider = $this->aiProviderManager->createInstance($provider_id);

      $chat_input = new ChatInput([
        new ChatMessage('system', $system_prompt),
        new ChatMessage('user', $user_input),
      ]);

      $response = $provider->chat($chat_input, $model_id)->getNormalized();
      $text_response = $response->getText();

      // Save the result back to ECA's state using ecaTokenServices.
      if ($this->ecaTokenServices) {
          $this->ecaTokenServices->addTokenData($response_token, $text_response);
      } else {
         $this->logger->warning('ECA token service not available to save response.');
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Error executing Fabric Pattern via AI: @message', ['@message' => $e->getMessage()]);
    }
  }

}
