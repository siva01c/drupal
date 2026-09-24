<?php

namespace Drupal\startupfactory_project\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\startupfactory_project\Service\ExportService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for exporting a project to Git.
 */
class ExportForm extends ContentEntityForm {

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
    $instance->exportService = $container->get('startupfactory_project.export');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $entity = $this->entity;

    // Check if already exported.
    if ($this->exportService->isExported($entity)) {
      $export_path = $this->exportService->getExportPath($entity);

      $form['export_status'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['export-status', 'export-status--exists']],
      ];

      $form['export_status']['message'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['messages', 'messages--status']],
        '#value' => $this->t('This project has already been exported to: @path', [
          '@path' => $export_path,
        ]),
      ];

      $form['export_status']['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['export-actions']],
      ];

      $form['export_status']['actions']['re_export'] = [
        '#type' => 'submit',
        '#value' => $this->t('Re-export (Overwrite)'),
        '#submit' => ['::submitFormReExport'],
        '#attributes' => ['class' => ['button--danger']],
      ];

      $form['export_status']['actions']['view_files'] = [
        '#type' => 'link',
        '#title' => $this->t('View Exported Files'),
        '#url' => \Drupal\Core\Url::fromUri('file://' . $export_path),
        '#attributes' => ['class' => ['button']],
      ];

      return $form;
    }

    // Export configuration options.
    $form['export_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Export Configuration'),
      '#open' => TRUE,
      '#attributes' => ['class' => ['export-options']],
    ];

    $form['export_options']['ci_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('CI/CD Provider'),
      '#description' => $this->t('Select which CI/CD workflow template to include.'),
      '#options' => [
        'github' => $this->t('GitHub Actions'),
        'gitlab' => $this->t('GitLab CI'),
      ],
      '#default_value' => 'github',
    ];

    $form['export_options']['include_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include content'),
      '#description' => $this->t('Export database content along with configuration.'),
      '#default_value' => FALSE,
    ];

    $form['export_options']['include_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include uploaded files'),
      '#description' => $this->t('Export files from sites/default/files.'),
      '#default_value' => FALSE,
    ];

    // Summary.
    $form['export_summary'] = [
      '#type' => 'details',
      '#title' => $this->t('Export Summary'),
      '#open' => TRUE,
    ];

    $form['export_summary']['project_info'] = [
      '#type' => 'item',
      '#markup' => $this->t('Project: @name (@type)', [
        '@name' => $entity->getName(),
        '@type' => $entity->getProjectType(),
      ]),
    ];

    $form['export_summary']['export_path'] = [
      '#type' => 'item',
      '#markup' => $this->t('Export to: @path', [
        '@path' => $this->exportService->getExportPath($entity),
      ]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The actual export is handled by the controller batch operation.
    // This form just validates options.
  }

  /**
   * Submit handler for re-export.
   */
  public function submitFormReExport(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('startupfactory_project.export', [
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
