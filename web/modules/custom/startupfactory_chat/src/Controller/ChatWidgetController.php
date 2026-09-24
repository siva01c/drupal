<?php

namespace Drupal\startupfactory_chat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\startupfactory_chat\Service\ChatIntegrationService;
use Drupal\startupfactory_chat\Service\JwtService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for chat widget configuration.
 */
class ChatWidgetController extends ControllerBase {

  /**
   * The chat integration service.
   *
   * @var \Drupal\startupfactory_chat\Service\ChatIntegrationService
   */
  protected $chatIntegration;

  /**
   * The JWT service.
   *
   * @var \Drupal\startupfactory_chat\Service\JwtService
   */
  protected $jwtService;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new ChatWidgetController.
   */
  public function __construct(
    ChatIntegrationService $chat_integration,
    JwtService $jwt_service,
    ModuleHandlerInterface $module_handler
  ) {
    $this->chatIntegration = $chat_integration;
    $this->jwtService = $jwt_service;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('startupfactory_chat.integration'),
      $container->get('startupfactory_chat.jwt_service'),
      $container->get('module_handler')
    );
  }

  /**
   * Serves the chat widget as a self-contained JavaScript file.
   *
   * The JS reads configuration from window.StartupFactoryChatConfig
   * which is injected as a <script> block before the widget code.
   * Embed with: <script src="/embed/startupfactory-chat.js?project_id=42"></script>
   */
  public function serveWidget(Request $request): Response {
    // Get project ID from query parameter or domain.
    $project_id = $request->query->get('project_id');

    if (!$project_id) {
      $host = $request->getHost();
      $project_id = $this->getProjectIdFromDomain($host);
    }

    if (!$project_id) {
      return new Response(
        '// Startup Factory Chat: Project context not found.',
        404,
        ['Content-Type' => 'application/javascript']
      );
    }

    $storage = $this->entityTypeManager()->getStorage('project');
    $project = $storage->load($project_id);

    if (!$project) {
      return new Response(
        '// Startup Factory Chat: Project not found.',
        404,
        ['Content-Type' => 'application/javascript']
      );
    }

    // Generate JWT token.
    $user_id = $this->currentUser()->id();
    $token = $this->jwtService->generateLocalToken($user_id, [
      'project_id' => $project_id,
      'session_id' => $this->jwtService->getSessionId(),
    ]);

    // Get ragchat shop ID.
    $shop_id = $this->getShopIdForProject($project_id);

    // Build widget configuration.
    $config = [
      'project_id' => (int) $project_id,
      'shop_id' => $shop_id,
      'token' => $token,
      'api_endpoint' => '/api/chat',
      'ragchat_url' => $this->chatIntegration->getBaseUrl(),
      'theme' => 'light',
      'greeting' => 'Hi! I can help you with your project. What would you like to do?',
      'placeholder' => 'Ask me anything...',
      'height' => '400px',
      'ragchat_available' => $this->chatIntegration->isServiceAvailable(),
    ];

    // Read the static widget JS file.
    $module_path = $this->moduleHandler->getModule('startupfactory_chat')->getPath();
    $js_file = DRUPAL_ROOT . '/' . $module_path . '/js/chat-widget.js';

    if (!file_exists($js_file)) {
      return new Response(
        '// Startup Factory Chat: Widget JS file not found.',
        500,
        ['Content-Type' => 'application/javascript']
      );
    }

    $js_code = file_get_contents($js_file);

    // Build output: config block + widget JS.
    $config_json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $output = "// Startup Factory Chat Widget — auto-generated config\n"
      . "window.StartupFactoryChatConfig = {$config_json};\n\n"
      . $js_code;

    return new Response($output, 200, [
      'Content-Type' => 'application/javascript',
      'Cache-Control' => 'public, max-age=300',
    ]);
  }

  /**
   * Get project ID from domain.
   */
  protected function getProjectIdFromDomain(string $domain): ?int {
    // Remove port if present.
    $domain = explode(':', $domain)[0];

    // Query for project with matching domain.
    $storage = $this->entityTypeManager()->getStorage('project');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1);

    $project_ids = $query->execute();

    foreach ($project_ids as $project_id) {
      $project = $storage->load($project_id);
      $project_domain = $project->getDomain();
      if ($project_domain && $project_domain->getHostname() === $domain) {
        return (int) $project_id;
      }
    }

    return NULL;
  }

  /**
   * Get ragchat shop ID for a project.
   */
  protected function getShopIdForProject(int $project_id): ?int {
    $storage = $this->entityTypeManager()->getStorage('project');
    $project = $storage->load($project_id);

    if (!$project) {
      return NULL;
    }

    // Try entity field first.
    if ($project->hasField('ragchat_shop_id')) {
      $shop_id = $project->get('ragchat_shop_id')->value;
      if ($shop_id) {
        return (int) $shop_id;
      }
    }

    // Fallback: direct DB query (works even when entity field cache is stale).
    $row = \Drupal::database()->query(
      "SELECT ragchat_shop_id FROM project WHERE id = :id",
      [':id' => $project_id]
    )->fetch();
    if ($row && !empty($row->ragchat_shop_id)) {
      return (int) $row->ragchat_shop_id;
    }

    return NULL;
  }

}
