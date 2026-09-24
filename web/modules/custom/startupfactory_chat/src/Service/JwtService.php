<?php

namespace Drupal\startupfactory_chat\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * JWT token management for ragchat communication.
 */
class JwtService {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user proxy.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new JwtService.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    AccountProxyInterface $current_user,
    RequestStack $request_stack,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->logger = $logger_factory->get('startupfactory_chat');
  }

  /**
   * Generate a local JWT token for chat widget authentication.
   */
  public function generateLocalToken(int $user_id, array $additional_claims = []): string {
    $header = $this->base64UrlEncode(json_encode([
      'alg' => 'HS256',
      'typ' => 'JWT',
    ]));

    $payload_data = array_merge([
      'uid' => $user_id,
      'iat' => time(),
      'exp' => time() + 3600,
      'iss' => 'startupfactory',
    ], $additional_claims);

    $payload = $this->base64UrlEncode(json_encode($payload_data));

    $signature = $this->base64UrlEncode(
      hash_hmac('sha256', "$header.$payload", $this->getSecretKey(), TRUE)
    );

    return "$header.$payload.$signature";
  }

  /**
   * Validate a local JWT token.
   */
  public function validateLocalToken(string $token): ?array {
    try {
      $parts = explode('.', $token);
      if (count($parts) !== 3) {
        return NULL;
      }

      [$header, $payload, $signature] = $parts;

      // Verify signature.
      $expected_signature = $this->base64UrlEncode(
        hash_hmac('sha256', "$header.$payload", $this->getSecretKey(), TRUE)
      );

      if (!hash_equals($expected_signature, $signature)) {
        return NULL;
      }

      $payload_data = json_decode($this->base64UrlDecode($payload), TRUE);

      // Check expiration.
      if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
        return NULL;
      }

      return $payload_data;

    }
    catch (\Exception $e) {
      $this->logger->error('JWT validation failed: @message', [
        '@message' => $e->getMessage(),
      ]);

      return NULL;
    }
  }

  /**
   * Get JWT secret key.
   */
  protected function getSecretKey(): string {
    $config = $this->configFactory->get('startupfactory_chat.settings');
    $secret = $config->get('jwt_secret') ?? '';

    if (empty($secret)) {
      // Generate a random secret if not configured.
      $secret = bin2hex(random_bytes(32));
      $this->configFactory->getEditable('startupfactory_chat.settings')
        ->set('jwt_secret', $secret)
        ->save();
    }

    return $secret;
  }

  /**
   * Base64 URL encode.
   */
  protected function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

  /**
   * Base64 URL decode.
   */
  protected function base64UrlDecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
  }

  /**
   * Get current user's session ID for anonymous users.
   */
  public function getSessionId(): string {
    $request = $this->requestStack->getCurrentRequest();
    return $request->getSession()->getId() ?? 'no-session';
  }

}
