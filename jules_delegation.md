# Technical Specifications: ECA Rules Engine Integration

This document outlines the detailed architectural requirements and API contracts for implementing the **ECA (Event-Condition-Action) integration** for the `ai_fabric` module.

> [!IMPORTANT]
> **Workspace Context**: You are working on the branch `jules/eca-integration` in an offline, local context without access to DDEV or a running Drupal database. Use this document as your strict guide and API reference.

---

## 🛠️ Feature Requirements
The goal is to allow site builders to use standard ECA models to trigger a **Fabric Pattern** automatically when an event occurs in Drupal (e.g., when a comment is saved, a node is updated, or an email is received).

You need to implement a **Configurable ECA Action Plugin** that:
1. Lets the user select a synchronized `fabric_pattern` configuration entity.
2. Accepts a customized text input (using standard Drupal tokens, e.g. `[node:body:value]`).
3. Lets the user select an AI provider and model configured in the core `ai` module.
4. Generates the response via the selected LLM and saves it to a custom ECA token name for downstream actions.

---

## 📂 File Structure to Create

Create the action plugin in the standard Drupal plugin directory:
```text
ai_fabric/
└── src/
    └── Plugin/
        └── Action/
            └── FabricEcaAction.php   <-- Create this file
```

---

## 💻 Technical Implementation Details

### 1. Plugin Declaration & Annotation
The plugin should be annotated as an Action plugin. It must implement `\Drupal\eca\Plugin\Action\ECAActionInterface` (usually via extending `ConfigurableActionBase`).

```php
namespace Drupal\ai_fabric\Plugin\Action;

use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Executes a Fabric Pattern via the Drupal AI ecosystem within ECA workflows.
 *
 * @Action(
 *   id = "ai_fabric_eca_run_pattern",
 *   label = @Translation("Run AI Fabric Pattern"),
 *   category = @Translation("AI Fabric")
 * )
 */
final class FabricEcaAction extends ConfigurableActionBase {
  // ...
}
```

### 2. Dependency Injection
Inject the following services via the constructor and `create()` method:
* `entity_type.manager` (ID: `entity_type.manager`): To load `fabric_pattern` config entities.
* `ai.provider` (ID: `ai.provider`): The `AiProviderPluginManager` from the core `ai` module to execute the chat inference.
* `token` (ID: `token`): To replace tokens in the system prompt and user input context.

### 3. Form Configuration Fields (`buildConfigurationForm`)
Provide the following fields to the site builder in the ECA UI:
* **Fabric Pattern** (`pattern_id`): A select element populated with all saved `fabric_pattern` configuration entities.
* **Context/User Input** (`user_input`): A textarea containing the prompt input (e.g. "Summarize: [node:body:value]"), supporting Drupal tokens.
* **AI Provider** (`provider_id`): A select element of available AI providers. You can get these via `$this->aiPluginManager->getAvailableProviders()`.
* **Model** (`model_id`): The model ID. Can be hardcoded or dynamically loaded.
* **Response Token Name** (`response_token`): A textfield where the LLM response will be saved (defaults to `ai_fabric_response`).

### 4. Execution Logic (`execute`)
In the `execute()` method:
1. Retrieve the configuration values from the plugin settings.
2. Load the chosen `fabric_pattern` entity using the Entity Storage.
3. Extract the `system_prompt` from the entity.
4. Replace tokens in both the `system_prompt` and `user_input` with the current event context:
   ```php
   $event = $this->getEvent(); // Get the event context from ECA
   // Replace tokens using the standard token service
   ```
5. Instantiate the AI provider:
   ```php
   /** @var \Drupal\ai\Plugin\ProviderProxy $provider */
   $provider = $this->aiPluginManager->createInstance($provider_id);
   ```
6. Build a standard `ChatInput` with `ChatMessage` containing:
   * A system instruction message containing the `system_prompt`.
   * A user message containing the `user_input`.
7. Execute the request:
   ```php
   $response = $provider->chat($chat_input, $model_id)->getNormalized();
   $text_response = $response->getText();
   ```
8. Save the `$text_response` back into ECA's state under the configured `response_token` so subsequent actions can reference it.

---

## 🛡️ Coding Guidelines
* **Strict Typing**: Use `declare(strict_types=1);` and full typing on parameters and returns.
* **Clean Code**: Ensure proper Docblocks, no debug statements, and keep it extremely clean and readable.
* **Defensive Programming**: Validate that the `fabric_pattern` exists before executing. Gracefully catch API exceptions from the `ai` module and log them using a logger channel.
