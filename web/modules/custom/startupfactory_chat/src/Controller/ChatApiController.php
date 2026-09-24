<?php

namespace Drupal\startupfactory_chat\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\startupfactory_chat\Service\ChatIntegrationService;
use Drupal\startupfactory_chat\Service\JwtService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for chat API proxy.
 */
class ChatApiController extends ControllerBase {

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
   * Constructs a new ChatApiController.
   */
  public function __construct(
    ChatIntegrationService $chat_integration,
    JwtService $jwt_service
  ) {
    $this->chatIntegration = $chat_integration;
    $this->jwtService = $jwt_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('startupfactory_chat.integration'),
      $container->get('startupfactory_chat.jwt_service')
    );
  }

  /**
   * Handle chat message requests.
   */
  public function chat(Request $request): JsonResponse {
    // Only allow POST requests.
    if ($request->getMethod() !== 'POST') {
      return new JsonResponse(['error' => 'Method not allowed.'], 405);
    }

    // Parse request data.
    $data = json_decode($request->getContent(), TRUE);
    if (!$data || !isset($data['message'])) {
      return new JsonResponse(['error' => 'Message is required.'], 400);
    }

    $message = $data['message'];
    $conversation_id = $data['conversation_id'] ?? NULL;
    $project_id = $data['project_id'] ?? NULL;

    // Get user context.
    $user_id = $this->currentUser()->id();
    $shop_id = NULL;

    // Map project to ragchat shop ID if provided.
    if ($project_id) {
      $shop_id = $this->getShopIdForProject($project_id);
    }

    // Send message to ragchat.
    $result = $this->chatIntegration->sendChatMessage(
      $message,
      $conversation_id,
      $shop_id,
      $user_id ?: NULL
    );

    if ($result['success']) {
      return new JsonResponse([
        'success' => TRUE,
        'response' => $result['response'],
        'conversation_id' => $result['conversation_id'] ?? $conversation_id,
      ]);
    }

    return new JsonResponse([
      'success' => FALSE,
      'error' => $result['error'] ?? 'Chat service unavailable.',
    ], 500);
  }

  /**
   * Get chat status.
   */
  public function status(): JsonResponse {
    return new JsonResponse([
      'status' => 'online',
      'ragchat_available' => $this->chatIntegration->isServiceAvailable(),
      'timestamp' => time(),
    ]);
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

    if ($project->hasField('ragchat_shop_id')) {
      $shop_id = $project->get('ragchat_shop_id')->value;
      if ($shop_id) {
        return (int) $shop_id;
      }
    }

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
