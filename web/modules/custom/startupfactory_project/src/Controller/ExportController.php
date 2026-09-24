<?php

namespace Drupal\startupfactory_project\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\startupfactory_project\Service\ExportService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for project export operations.
 */
class ExportController extends ControllerBase {

  /**
   * The export service.
   *
   * @var \Drupal\startupfactory_project\Service\ExportService
   */
  protected $exportService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ExportController.
   */
  public function __construct(
    ExportService $export_service,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->exportService = $export_service;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('startupfactory_project.export'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Displays the export form for a project.
   */
  public function exportForm($project) {
    $entity = $this->entityTypeManager->getStorage('project')->load($project);

    if (!$entity) {
      $this->messenger()->addError($this->t('Project not found.'));
      return $this->redirect('entity.project.collection');
    }

    // Build the export form.
    $form_builder = \Drupal::formBuilder();
    $form = $form_builder->getForm('Drupal\startupfactory_project\Form\ExportForm', $entity);

    return $form;
  }

  /**
   * Initiates the export batch operation.
   */
  public function exportExecute($project) {
    $entity = $this->entityTypeManager->getStorage('project')->load($project);

    if (!$entity) {
      $this->messenger()->addError($this->t('Project not found.'));
      return $this->redirect('entity.project.collection');
    }

    // Get form state values for options.
    $options = [
      'ci_provider' => 'github',
      'include_content' => FALSE,
      'include_files' => FALSE,
    ];

    // Build batch operation.
    $batch = [
      'title' => $this->t('Exporting project: @name', ['@name' => $entity->getName()]),
      'operations' => [
        [
          [$this, 'batchExportProject'],
          [$entity->id(), $options],
        ],
      ],
      'finished' => [$this, 'batchExportFinished'],
      'init_message' => $this->t('Preparing export...'),
      'progress_message' => $this->t('Exporting project (@current of @total)...'),
      'error_message' => $this->t('Export failed.'),
      'file' => \Drupal::root() . '/modules/custom/startupfactory_project/export.batch.inc',
    ];

    batch_set($batch);

    // Redirect to dashboard after batch completes.
    return $this->redirect('startupfactory_project.dashboard');
  }

  /**
   * Batch operation: Export project.
   */
  public function batchExportProject($project_id, $options, &$context) {
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
  public function batchExportFinished($success, $results, $operations) {
    $messenger = \Drupal::messenger();

    if ($success) {
      $result = $results['export_result'] ?? [];
      $project_name = $results['project_name'] ?? 'Unknown';

      if ($result['status'] === 'success') {
        $messenger->addStatus($this->t('Project "@name" exported successfully!', [
          '@name' => $project_name,
        ]));

        // Show export path.
        $path = $result['path'] ?? '';
        if ($path) {
          $messenger->addStatus($this->t('Export path: @path', ['@path' => $path]));
        }

        // Show messages.
        foreach ($result['messages'] as $message) {
          $messenger->addStatus($message);
        }
      }
      else {
        $messenger->addError($this->t('Export failed for project "@name".', [
          '@name' => $project_name,
        ]));

        foreach ($result['messages'] as $message) {
          $messenger->addError($message);
        }
      }
    }
    else {
      $error_operation = reset($operations);
      $messenger->addError($this->t('An error occurred while exporting: @error', [
        '@error' => $error_operation[0] ?? 'Unknown error',
      ]));
    }
  }

  /**
   * Downloads the exported project as a zip file.
   */
  public function downloadExport($project) {
    $entity = $this->entityTypeManager->getStorage('project')->load($project);

    if (!$entity) {
      $this->messenger()->addError($this->t('Project not found.'));
      return $this->redirect('entity.project.collection');
    }

    $export_path = $this->exportService->getExportPath($entity);

    if (!is_dir($export_path)) {
      $this->messenger()->addError($this->t('Export not found. Please export the project first.'));
      return $this->redirect('startupfactory_project.export_form', ['project' => $entity->id()]);
    }

    // Create zip file.
    $zip_file = tempnam(sys_get_temp_dir(), 'export_') . '.zip';
    $zip = new \ZipArchive();

    if ($zip->open($zip_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
      $this->messenger()->addError($this->t('Failed to create zip file.'));
      return $this->redirect('startupfactory_project.dashboard');
    }

    // Add files to zip.
    $this->addDirectoryToZip($zip, $export_path, $entity->getSlug());
    $zip->close();

    // Return file response.
    $response = new \Symfony\Component\HttpFoundation\Response(
      file_get_contents($zip_file),
      200,
      [
        'Content-Type' => 'application/zip',
        'Content-Disposition' => 'attachment; filename="' . $entity->getSlug() . '.zip"',
        'Content-Length' => filesize($zip_file),
      ]
    );

    unlink($zip_file);
    return $response;
  }

  /**
   * Recursively add a directory to a zip archive.
   */
  protected function addDirectoryToZip(\ZipArchive $zip, string $directory, string $prefix) {
    $files = scandir($directory);
    foreach ($files as $file) {
      if ($file === '.' || $file === '..') {
        continue;
      }

      $full_path = $directory . '/' . $file;
      $zip_path = $prefix . '/' . $file;

      if (is_dir($full_path)) {
        $zip->addEmptyDir($zip_path);
        $this->addDirectoryToZip($zip, $full_path, $zip_path);
      }
      else {
        $zip->addFile($full_path, $zip_path);
      }
    }
  }

}
