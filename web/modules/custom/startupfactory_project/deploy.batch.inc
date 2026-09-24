<?php

/**
 * @file
 * Batch operations for project deployment.
 */

/**
 * Batch operation: Deploy project.
 */
function startupfactory_project_batch_deploy_project($project_id, $options, &$context) {
  $storage = \Drupal::entityTypeManager()->getStorage('project');
  $entity = $storage->load($project_id);

  if (!$entity) {
    $context['results']['error'] = 'Project not found.';
    $context['finished'] = 1;
    return;
  }

  // Perform the deployment.
  $deploy_service = \Drupal::service('startupfactory_project.deploy');
  $result = $deploy_service->deploy($entity, $options);

  // Store results for finished callback.
  $context['results']['project_name'] = $entity->getName();
  $context['results']['deploy_result'] = $result;
  $context['finished'] = 1;
}

/**
 * Batch finished callback.
 */
function startupfactory_project_batch_deploy_finished($success, $results, $operations) {
  $messenger = \Drupal::messenger();

  if ($success) {
    $result = $results['deploy_result'] ?? [];
    $project_name = $results['project_name'] ?? 'Unknown';

    if ($result['status'] === 'success') {
      $messenger->addStatus(t('Project "@name" deployed successfully!', [
        '@name' => $project_name,
      ]));

      // Show deployment URL.
      $url = $result['url'] ?? '';
      if ($url) {
        $messenger->addStatus(t('Deployment URL: @url', ['@url' => $url]));
      }

      // Show messages.
      foreach ($result['messages'] as $message) {
        $messenger->addStatus($message);
      }
    }
    else {
      $messenger->addError(t('Deployment failed for project "@name".', [
        '@name' => $project_name,
      ]));

      foreach ($result['messages'] as $message) {
        $messenger->addError($message);
      }
    }
  }
  else {
    $error_operation = reset($operations);
    $messenger->addError(t('An error occurred while deploying: @error', [
      '@error' => $error_operation[0] ?? 'Unknown error',
    ]));
  }
}
