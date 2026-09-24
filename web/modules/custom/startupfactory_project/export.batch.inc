<?php

/**
 * @file
 * Batch operations for project export.
 */

/**
 * Batch operation: Export project.
 */
function startupfactory_project_batch_export_project($project_id, $options, &$context) {
  $storage = \Drupal::entityTypeManager()->getStorage('project');
  $entity = $storage->load($project_id);

  if (!$entity) {
    $context['results']['error'] = 'Project not found.';
    $context['finished'] = 1;
    return;
  }

  // Perform the export.
  $export_service = \Drupal::service('startupfactory_project.export');
  $result = $export_service->export($entity, $options);

  // Store results for finished callback.
  $context['results']['project_name'] = $entity->getName();
  $context['results']['export_result'] = $result;
  $context['finished'] = 1;
}

/**
 * Batch finished callback.
 */
function startupfactory_project_batch_export_finished($success, $results, $operations) {
  $messenger = \Drupal::messenger();

  if ($success) {
    $result = $results['export_result'] ?? [];
    $project_name = $results['project_name'] ?? 'Unknown';

    if ($result['status'] === 'success') {
      $messenger->addStatus(t('Project "@name" exported successfully!', [
        '@name' => $project_name,
      ]));

      // Show export path.
      $path = $result['path'] ?? '';
      if ($path) {
        $messenger->addStatus(t('Export path: @path', ['@path' => $path]));
      }

      // Show messages.
      foreach ($result['messages'] as $message) {
        $messenger->addStatus($message);
      }
    }
    else {
      $messenger->addError(t('Export failed for project "@name".', [
        '@name' => $project_name,
      ]));

      foreach ($result['messages'] as $message) {
        $messenger->addError($message);
      }
    }
  }
  else {
    $error_operation = reset($operations);
    $messenger->addError(t('An error occurred while exporting: @error', [
      '@error' => $error_operation[0] ?? 'Unknown error',
    ]));
  }
}
