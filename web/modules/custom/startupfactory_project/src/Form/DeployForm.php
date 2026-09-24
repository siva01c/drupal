<?php

namespace Drupal\startupfactory_project\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\startupfactory_project\Service\DeployService;
use Drupal\startupfactory_project\Service\ExportService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for deploying a project to VPS.
 */
class DeployForm extends ContentEntityForm {

  /**
   * The deploy service.
   *
   * @var \Drupal\startupfactory_project\Service\DeployService
   */
  protected $deployService;

  /**
   * The export service.
   *
   * @var \Drupal\startupfactory_project\Service\ExportService
   */
  protected $exportService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->deployService = $container->get('startupfactory_project.deploy');
    $instance->exportService = $container->get('startupfactory_project.export');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $entity = $this->entity;

    // Check if project is exported.
    if (!$this->exportService->isExported($entity)) {
      $form['not_exported'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['deploy-status', 'deploy-status--not-exported']],
      ];

      $form['not_exported']['message'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        '#value' => $this->t('This project must be exported before deployment. Please export the project first.'),
      ];

      $form['not_exported']['export_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Export to Git'),
        '#url' => \Drupal\Core\Url::fromRoute('startupfactory_project.export_form', ['project' => $entity->id()]),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];

      return $form;
    }

    // Check deployment status.
    $is_deployed = $this->deployService->isDeployed($entity);
    $deploy_status = $this->deployService->getDeploymentStatus($entity);

    if ($is_deployed) {
      $deploy_path = $this->deployService->getDeploymentPath($entity);
      $domain = $entity->getDomain();
      $url = $domain ? 'https://' . $domain->getHostname() : '';

      $form['deploy_status'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['deploy-status', 'deploy-status--exists']],
      ];

      $status_class = $deploy_status === 'running' ? 'status' : 'warning';
      $status_text = $deploy_status === 'running' ? $this->t('Running') : $this->t('Stopped');

      $form['deploy_status']['message'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['messages', 'messages--' . $status_class]],
        '#value' => $this->t('This project is deployed at @path. Status: @status', [
          '@path' => $deploy_path,
          '@status' => $status_text,
        ]),
      ];

      if ($url) {
        $form['deploy_status']['url'] = [
          '#type' => 'link',
          '#title' => $this->t('Visit Site'),
          '#url' => \Drupal\Core\Url::fromUri($url),
          '#attributes' => ['class' => ['button'], 'target' => '_blank'],
        ];
      }

      $form['deploy_status']['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['deploy-actions']],
      ];

      $form['deploy_status']['actions']['redeploy'] = [
        '#type' => 'submit',
        '#value' => $this->t('Redeploy'),
        '#submit' => ['::submitFormRedeploy'],
      ];

      $form['deploy_status']['actions']['stop'] = [
        '#type' => 'submit',
        '#value' => $this->t('Stop'),
        '#submit' => ['::submitFormStop'],
        '#attributes' => ['class' => ['button--danger']],
      ];

      return $form;
    }

    // Deployment configuration options.
    $form['deploy_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Deployment Configuration'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['deploy-options']],
    ];

    $form['deploy_options']['db_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Database Password'),
      '#description' => $this->t('Password for the Drupal database user.'),
      '#default_value' => 'drupal',
      '#required' => TRUE,
    ];

    $form['deploy_options']['mysql_root_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('MySQL Root Password'),
      '#description' => $this->t('Password for the MySQL root user.'),
      '#default_value' => 'root',
      '#required' => TRUE,
    ];

    // Summary.
    $form['deploy_summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Deployment Summary'),
      '#open' => TRUE,
    ];

    $form['deploy_summary']['project_info'] = [
      '#type' => 'item',
      '#markup' => $this->t('Project: @name (@type)', [
        '@name' => $entity->getName(),
        '@type' => $entity->getProjectType(),
      ]),
    ];

    $form['deploy_summary']['domain_info'] = [
      '#type' => 'item',
      '#markup' => $this->t('Domain: @domain', [
        '@domain' => $entity->getDomain() ? $entity->getDomain()->getHostname() : $this->t('Not configured'),
      ]),
    ];

    $form['deploy_summary']['deploy_path'] = [
      '#type' => 'item',
      '#markup' => $this->t('Deploy to: @path', [
        '@path' => $this->deployService->getDeploymentPath($entity),
      ]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The actual deployment is handled by the controller batch operation.
    // This form just validates options.
  }

  /**
   * Submit handler for redeploy.
   */
  public function submitFormRedeploy(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('startupfactory_project.deploy_execute', [
      'project' => $this->entity->id(),
    ]);
  }

  /**
   * Submit handler for stop.
   */
  public function submitFormStop(array &$form, FormStateInterface $form_state) {
    $this->deployService->stopDeployment($this->entity);
    $this->messenger()->addStatus($this->t('Deployment stopped.'));
    $form_state->setRedirect('startupfactory_project.deploy_form', [
      'project' => $this->entity->id(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

}
