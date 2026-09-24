<?php

namespace Drupal\startupfactory_project\Entity;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access control handler for the Project entity.
 */
class ProjectAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {
      case 'view':
        if ($entity->isPublished() || $entity->getOwnerId() === $account->id()) {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden()->addCacheableDependency($entity);

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit any project entities')
          ->orIf(AccessResult::allowedIfHasPermission($account, 'edit own project entities')
            ->addCacheableDependency($entity)
            ->addCacheContexts(['user.permissions']));

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete any project entities')
          ->orIf(AccessResult::allowedIfHasPermission($account, 'delete own project entities')
            ->addCacheableDependency($entity)
            ->addCacheContexts(['user.permissions']));

      default:
        return AccessResult::neutral();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'create project entities');
  }

}
