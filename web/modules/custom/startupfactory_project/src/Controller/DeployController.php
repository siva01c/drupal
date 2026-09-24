<?php

namespace Drupal\startupfactory_project\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\startupfactory_project\Service\DeployService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for project deployment operations.
 */
class DeployController extends ControllerBase {

  /**
   * The deploy service.
   *
   * @var \Drupal\startupfactory_project\Service\DeployService
   */
  protected $deployService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DeployController.
   */
  public function __construct(
    DeployService $deploy_service,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->deployService = $deploy_service;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('startupfactory_project.deploy'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Displays the deploy form for a project.
   */
  public function deployForm($project) {
    $entity = $this->entityTypeManager->getStorage('project')->load($project);

    if (!$entity) {
      $this->messenger()->addError($this->t('Project not found.'));
      return $this->redirect('entity.project.collection');
    }

    // Build the deploy form.
    $form_builder = \Drupal::formBuilder();
    $form = $form_builder->getForm('Drupal\startupfactory_project\Form\DeployForm', $entity);

    return $form;
  }

  /**
   * Initiates the deployment batch operation.
   */
  public function deployExecute($project) {
    $entity = $this->entityTypeManager->getStorage('project')->load($project);

    if (!$entity) {
      $this->messenger()->addError($this->t('Project not found.'));
      return $this->redirect('entity.project.collection');
    }

    // Get form state values for options.
    $options = [
      'db_password' => 'drupal',
      'mysql_root_password' => 'root',
    ];

    // Build batch operation.
    $batch = [
      'title' => $this->t('Deploying project: @name', ['@name' => $entity->getName()]),
      'operations' => [
        [
          [$this, 'batchDeployProject'],
          [$entity->id(), $options],
        ],
      ],
      'finished' => [$this, 'batchDeployFinished'],
      'init_message' => $this->t('Preparing deployment...'),
      'progress_message' => $this->t('Deploying project (@current of @total)...'),
      'error_message' => $this->t('Deployment failed.'),
      'file' => \Drupal::root() . '/modules/custom/startupfactory_project/deploy.batch.inc',
    ];

    batch_set($batch);

    // Redirect to dashboard after batch completes.
    return $this->redirect('startupfactory_project.dashboard');
  }

  /**
   * Batch operation: Deploy project.
   */
  public function batchDeployProject($project_id, $options, &$context) {
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
  public function batchDeployFinished($success, $results, $operations) {
    $messenger = \Drupal::messenger();

    if ($success) {
      $result = $results['deploy_result'] ?? [];
      $project_name = $results['project_name'] ?? 'Unknown';

      if ($result['status'] === 'success') {
        $messenger->addStatus($this->t('Project "@name" deployed successfully!', [
          '@name' => $project_name,
        ]));

        // Show deployment URL.
        $url = $result['url'] ?? '';
        if ($url) {
          $messenger->addStatus($this->t('Deployment URL: @url', ['@url' => $url]));
        }

        // Show messages.
        foreach ($result['messages'] as $message) {
          $messenger->addStatus($message);
        }
      }
      else {
        $messenger->addError($this->t('Deployment failed for project "@name".', [
          '@name' => $project_name,
        ]));

        foreach ($result['messages'] as $message) {
          $messenger->addError($message);
        }
      }
    }
    else {
      $error_operation = reset($operations);
      $messenger->addError($this->t('An error occurred while deploying: @error', [
        '@error' => $error_operation[0] ?? 'Unknown error',
      ]));
    }
  }

}
