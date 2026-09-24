<?php

namespace Drupal\startupfactory_chat\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Service for communicating with ragchat API.
 */
class ChatIntegrationService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The key repository.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ChatIntegrationService.
   */
  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    KeyRepositoryInterface $key_repository,
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->keyRepository = $key_repository;
    $this->logger = $logger_factory->get('startupfactory_chat');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get ragchat base URL from configuration.
   */
  public function getBaseUrl(): string {
    $config = $this->configFactory->get('startupfactory_chat.settings');
    return $config->get('ragchat_base_url') ?? 'http://ragchat.local:8080';
  }

  /**
   * Get ragchat API token from key module.
   */
  public function getApiToken(): ?string {
    $key = $this->keyRepository->getKey('startupfactory_ragchat_token');
    if ($key) {
      $key_value = $key->getKeyValue();
      if ($key_value) {
        return $key_value;
      }
    }

    // Fallback to config.
    $config = $this->configFactory->get('startupfactory_chat.settings');
    return $config->get('ragchat_api_token') ?? NULL;
  }

  /**
   * Send chat message to ragchat.
   *
   * @param string $message
   *   The user message.
   * @param string|null $conversation_id
   *   The conversation ID for context.
   * @param int|null $shop_id
   *   The ragchat shop ID (project's ragchat node ID).
   * @param int|null $user_id
   *   The user ID.
   *
   * @return array
   *   Response with 'success', 'response', 'conversation_id' keys.
   */
  public function sendChatMessage(string $message, ?string $conversation_id = NULL, ?int $shop_id = NULL, ?int $user_id = NULL): array {
    try {
      // First get a JWT token.
      $token_data = $this->getJwtToken($shop_id);
      if (!$token_data || !isset($token_data['token'])) {
        return [
          'success' => FALSE,
          'error' => 'Failed to authenticate with chat service.',
        ];
      }

      $jwt_token = $token_data['token'];
      $csrf_token = $token_data['csrf_token'] ?? NULL;
      $csrf_headers = $token_data['csrf_headers'] ?? [];

      // Build request payload.
      $payload = [
        'message' => $message,
      ];

      if ($conversation_id) {
        $payload['conversation_id'] = $conversation_id;
      }

      if ($shop_id) {
        $payload['shop_context'] = ['shop_id' => $shop_id];
      }

      if ($user_id) {
        $payload['user_id'] = $user_id;
      }

      // Send request to ragchat.
      $headers = [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $jwt_token,
      ];

      // Add CSRF headers if available.
      if ($csrf_token && !empty($csrf_headers)) {
        foreach ($csrf_headers as $header_name => $header_value) {
          $headers[$header_name] = $header_value . ' ' . $csrf_token;
        }
      }

      $response = $this->httpClient->request(
        'POST',
        $this->getBaseUrl() . '/api/ragchat',
        [
          'headers' => $headers,
          'json' => $payload,
          'timeout' => 30,
        ]
      );

      $body = json_decode($response->getBody()->getContents(), TRUE);

      if (isset($body['response'])) {
        return [
          'success' => TRUE,
          'response' => $body['response'],
          'conversation_id' => $body['conversation_id'] ?? $conversation_id,
        ];
      }

      return [
        'success' => FALSE,
        'error' => $body['error'] ?? 'Unknown error from chat service.',
      ];

    }
    catch (\Exception $e) {
      $this->logger->error('Chat message failed: @message', [
        '@message' => $e->getMessage(),
      ]);

      return [
        'success' => FALSE,
        'error' => 'Failed to communicate with chat service: ' . $e->getMessage(),
      ];
    }
  }

  /**
   * Get JWT token from ragchat for chat authentication.
   */
  protected function getJwtToken(?int $shop_id = NULL): ?array {
    try {
      $payload = [];
      if ($shop_id) {
        $payload['shop_context'] = ['shop_id' => $shop_id];
      }

      $headers = [
        'Content-Type' => 'application/json',
      ];

      // Use API token for authentication.
      $api_token = $this->getApiToken();
      if ($api_token) {
        $headers['Authorization'] = 'Basic ' . base64_encode($api_token);
      }

      $response = $this->httpClient->request(
        'POST',
        $this->getBaseUrl() . '/api/ragchat/token',
        [
          'headers' => $headers,
          'json' => $payload,
          'timeout' => 10,
        ]
      );

      return json_decode($response->getBody()->getContents(), TRUE);

    }
    catch (\Exception $e) {
      $this->logger->error('JWT token request failed: @message', [
        '@message' => $e->getMessage(),
      ]);

      return NULL;
    }
  }

  /**
   * Check if ragchat service is available.
   */
  public function isServiceAvailable(): bool {
    try {
      $response = $this->httpClient->request(
        'GET',
        $this->getBaseUrl() . '/api/ragchat/status',
        [
          'timeout' => 5,
          'headers' => [
            'Authorization' => 'Basic ' . base64_encode($this->getApiToken() ?? ''),
          ],
        ]
      );

      $body = json_decode($response->getBody()->getContents(), TRUE);
      return ($body['status'] ?? '') === 'online';

    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Get widget configuration for a project.
   */
  public function getWidgetConfig(int $shop_id): array {
    try {
      $response = $this->httpClient->request(
        'GET',
        $this->getBaseUrl() . '/api/embed/config/' . $shop_id,
        [
          'timeout' => 10,
        ]
      );

      return json_decode($response->getBody()->getContents(), TRUE);

    }
    catch (\Exception $e) {
      $this->logger->warning('Widget config fetch failed for shop @id: @message', [
        '@id' => $shop_id,
        '@message' => $e->getMessage(),
      ]);

      return [];
    }
  }

  /**
   * Create a shop node in ragchat for a startupfactory project.
   */
  public function createShopForProject(int $project_id, string $project_name, string $domain): ?int {
    try {
      $tool_name = $this->resolveMcpToolName('create_entity');
      if (!$tool_name) {
        $this->logger->error('Could not resolve MCP tool name for create_entity.');
        return NULL;
      }

      $result = $this->executeMcpTool($tool_name, [
        'entity_type' => 'node',
        'bundle' => 'shop',
        'field_values' => [
          'title' => $project_name,
          'status' => 1,
          'field_shop_domain' => $domain,
          'field_startupfactory_project_id' => $project_id,
        ],
      ]);

      if ($result && !empty($result['data']['id'])) {
        return (int) $result['data']['id'];
      }

      return NULL;

    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create shop for project @id: @message', [
        '@id' => $project_id,
        '@message' => $e->getMessage(),
      ]);

      return NULL;
    }
  }

  /**
   * Resolve an MCP tool name (with hash suffix) from its original name.
   *
   * MCP tools are registered as "{plugin_id}_{md5hash}". This method
   * queries tools/list and returns the full tool name matching the
   * given original name prefix.
   */
  public function resolveMcpToolName(string $original_name): ?string {
    try {
      $api_token = $this->getApiToken();
      if (!$api_token) {
        return NULL;
      }

      $response = $this->httpClient->request(
        'POST',
        $this->getBaseUrl() . '/mcp/post',
        [
          'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($api_token),
          ],
          'json' => [
            'jsonrpc' => '2.0',
            'id' => uniqid('sf_list_', TRUE),
            'method' => 'tools/list',
            'params' => [],
          ],
          'timeout' => 15,
        ]
      );

      $raw = $response->getBody()->getContents();
      $body = json_decode($raw, TRUE);

      // Handle xdebug HTML prepended to JSON-RPC response.
      if (!is_array($body)) {
        $jsonStart = strrpos($raw, '{"jsonrpc"');
        if ($jsonStart !== FALSE) {
          $body = json_decode(substr($raw, $jsonStart), TRUE);
        }
      }

      $tools = $body['result']['tools'] ?? [];

      foreach ($tools as $tool) {
        $name = $tool['name'] ?? '';
        $desc = $tool['description'] ?? '';
        if (str_starts_with($name, $original_name . '_') || str_contains($desc, 'Original name: ' . $original_name . ',')) {
          return $name;
        }
      }

      return NULL;
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Execute MCP tool on ragchat for project configuration.
   */
  public function executeMcpTool(string $tool_name, array $arguments): ?array {
    try {
      $api_token = $this->getApiToken();
      if (!$api_token) {
        return NULL;
      }

      $jsonrpc_payload = [
        'jsonrpc' => '2.0',
        'id' => uniqid('sf_', TRUE),
        'method' => 'tools/call',
        'params' => [
          'name' => $tool_name,
          'arguments' => $arguments,
        ],
      ];

      $response = $this->httpClient->request(
        'POST',
        $this->getBaseUrl() . '/mcp/post',
        [
          'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($api_token),
          ],
          'json' => $jsonrpc_payload,
          'timeout' => 30,
        ]
      );

      $raw = $response->getBody()->getContents();
      $body = json_decode($raw, TRUE);

      // When xdebug is enabled, HTML error output may be prepended to the
      // JSON-RPC response. Find the last JSON object in the body.
      if (!is_array($body)) {
        $jsonStart = strrpos($raw, '{"jsonrpc"');
        if ($jsonStart !== FALSE) {
          $body = json_decode(substr($raw, $jsonStart), TRUE);
        }
      }

      if (isset($body['result']['content'][0]['text'])) {
        return json_decode($body['result']['content'][0]['text'], TRUE);
      }

      if (isset($body['error'])) {
        $this->logger->error('MCP RPC error for @tool: @msg', [
          '@tool' => $tool_name,
          '@msg' => $body['error']['message'] ?? 'unknown',
        ]);
      }

      return NULL;

    }
    catch (\Exception $e) {
      $this->logger->error('MCP tool execution failed: @tool - @message', [
        '@tool' => $tool_name,
        '@message' => $e->getMessage(),
      ]);

      return NULL;
    }
  }

}
