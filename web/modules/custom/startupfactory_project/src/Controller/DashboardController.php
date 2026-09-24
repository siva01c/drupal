<?php

namespace Drupal\startupfactory_project\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for the project dashboard.
 */
class DashboardController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DashboardController.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Renders the user's project dashboard.
   */
  public function dashboard() {
    $build = [];

    $build['#attached']['library'][] = 'startupfactory_project/dashboard';

    $build['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h1',
      '#value' => $this->t('My Projects'),
    ];

    // Get current user's projects.
    $storage = $this->entityTypeManager->getStorage('project');
    $query = $storage->getQuery()
      ->condition('uid', $this->currentUser()->id())
      ->condition('status', 1)
      ->sort('created', 'DESC');
    $project_ids = $query->execute();
    $projects = $storage->loadMultiple($project_ids);

    if (empty($projects)) {
      $build['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('You have no projects yet.'),
        '#attributes' => ['class' => ['dashboard-empty']],
      ];

      $build['create_link'] = [
        '#type' => 'link',
        '#title' => $this->t('+ Create your first project'),
        '#url' => Url::fromRoute('entity.project.add_form'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ];

      return $build;
    }

    // Build project cards.
    $build['projects'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['project-dashboard']],
    ];

    foreach ($projects as $project) {
      $domain = $project->getDomain();
      $type_labels = [
        'web' => 'Web App',
        'eshop' => 'E-Shop',
        'chatbot' => 'AI Chatbot',
        'saas' => 'SaaS Platform',
      ];

      $build['projects'][$project->id()] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['project-card']],
      ];

      $build['projects'][$project->id()]['name'] = [
        '#type' => 'link',
        '#title' => $project->getName(),
        '#url' => Url::fromRoute('entity.project.canonical', ['project' => $project->id()]),
        '#attributes' => ['class' => ['project-card__title']],
      ];

      $build['projects'][$project->id()]['meta'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['project-card__meta']],
        '#value' => $this->t('@type | @domain | @visibility', [
          '@type' => $type_labels[$project->getProjectType()] ?? $project->getProjectType(),
          '@domain' => $domain ? $domain->getHostname() : $this->t('No domain'),
          '@visibility' => $project->isPublic() ? $this->t('Public') : $this->t('Private'),
        ]),
      ];

      $build['projects'][$project->id()]['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['project-card__actions']],
      ];

      $build['projects'][$project->id()]['actions']['edit'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#url' => Url::fromRoute('entity.project.edit_form', ['project' => $project->id()]),
        '#attributes' => ['class' => ['button']],
      ];

      if ($domain) {
        $build['projects'][$project->id()]['actions']['view'] = [
          '#type' => 'link',
          '#title' => $this->t('View Site'),
          '#url' => Url::fromUri('http://' . $domain->getHostname()),
          '#attributes' => ['class' => ['button'], 'target' => '_blank'],
        ];
      }

      $build['projects'][$project->id()]['actions']['export'] = [
        '#type' => 'link',
        '#title' => $this->t('Export to Git'),
        '#url' => Url::fromRoute('startupfactory_project.export_form', ['project' => $project->id()]),
        '#attributes' => ['class' => ['button', 'button--export']],
      ];

      $build['projects'][$project->id()]['actions']['deploy'] = [
        '#type' => 'link',
        '#title' => $this->t('Deploy'),
        '#url' => Url::fromRoute('startupfactory_project.deploy_form', ['project' => $project->id()]),
        '#attributes' => ['class' => ['button', 'button--deploy']],
      ];
    }

    // Add "Create new project" button.
    $build['create_link'] = [
      '#type' => 'link',
      '#title' => $this->t('+ Create New Project'),
      '#url' => Url::fromRoute('entity.project.add_form'),
      '#attributes' => ['class' => ['button', 'button--primary', 'project-dashboard__create']],
    ];

    return $build;
  }

  /**
   * Renders the public project catalog.
   */
  public function catalog() {
    $build = [];

    $build['#attached']['library'][] = 'startupfactory_project/dashboard';

    $build['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h1',
      '#value' => $this->t('Project Catalog'),
    ];

    // Get all public projects.
    $storage = $this->entityTypeManager->getStorage('project');
    $query = $storage->getQuery()
      ->condition('visibility', 1)
      ->condition('status', 1)
      ->sort('created', 'DESC');
    $project_ids = $query->execute();
    $projects = $storage->loadMultiple($project_ids);

    if (empty($projects)) {
      $build['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('No public projects available yet.'),
      ];
      return $build;
    }

    $build['catalog'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['project-catalog']],
    ];

    $type_labels = [
      'web' => 'Web App',
      'eshop' => 'E-Shop',
      'chatbot' => 'AI Chatbot',
      'saas' => 'SaaS Platform',
    ];

    foreach ($projects as $project) {
      $domain = $project->getDomain();
      $owner = $project->getOwner();

      $build['catalog'][$project->id()] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['project-catalog__item']],
      ];

      $build['catalog'][$project->id()]['name'] = [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $project->getName(),
      ];

      $build['catalog'][$project->id()]['meta'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['project-catalog__meta']],
        '#value' => $this->t('@type | by @owner | @domain', [
          '@type' => $type_labels[$project->getProjectType()] ?? $project->getProjectType(),
          '@owner' => $owner ? $owner->getDisplayName() : $this->t('Unknown'),
          '@domain' => $domain ? $domain->getHostname() : $this->t('No domain'),
        ]),
      ];

      if ($domain) {
        $build['catalog'][$project->id()]['link'] = [
          '#type' => 'link',
          '#title' => $this->t('Visit Site'),
          '#url' => Url::fromUri('http://' . $domain->getHostname()),
          '#attributes' => ['class' => ['button'], 'target' => '_blank'],
        ];
      }
    }

    return $build;
  }

}
