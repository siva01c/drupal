<?php

namespace Drupal\startupfactory_project\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\startupfactory_project\Entity\Project;
use Symfony\Component\Process\Process;

/**
 * Service for deploying projects to VPS via Git push and CI/CD.
 *
 * Deployment workflow:
 * 1. Export project to local directory (ExportService)
 * 2. Push exported code to Git remote (GitHub/GitLab)
 * 3. CI/CD pipeline deploys to VPS via ops-proxy
 */
class DeployService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Base path for deployment staging.
   *
   * @var string
   */
  protected $deployPath;

  /**
   * Constructs a new DeployService.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    LoggerChannelFactoryInterface $logger_factory,
    Settings $settings
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->logger = $logger_factory->get('startupfactory_project');

    // Use configured deploy path or default.
    $this->deployPath = $settings->get('startupfactory_deploy_path', '/tmp/startupfactory_deploys');
  }

  /**
   * Prepare and push a project for deployment via Git.
   *
   * @param \Drupal\startupfactory_project\Entity\Project $project
   *   The project entity to deploy.
   * @param array $options
   *   Deployment options (remote_url, branch, etc.).
   *
   * @return array
   *   Deployment result with status, messages, and remote URL.
   */
  public function deploy(Project $project, array $options = []) {
    $result = [
      'status' => 'error',
      'remote_url' => '',
      'messages' => [],
    ];

    try {
      // 1. Check if project is exported.
      $export_service = \Drupal::service('startupfactory_project.export');
      if (!$export_service->isExported($project)) {
        throw new \RuntimeException('Project must be exported before deployment. Please export first.');
      }

      // 2. Prepare deployment staging directory.
      $deploy_dir = $this->prepareDeploymentDirectory($project);
      $result['messages'][] = t('Deployment staging directory prepared: @path', ['@path' => $deploy_dir]);

      // 3. Sync exported files to staging directory.
      $this->syncExportedFiles($project, $deploy_dir);
      $result['messages'][] = t('Exported files synced to staging directory.');

      // 4. Configure environment for VPS deployment.
      $this->configureEnvironment($project, $deploy_dir, $options);
      $result['messages'][] = t('Environment configured for deployment.');

      // 5. Initialize Git and commit.
      $this->initGitRepo($deploy_dir);
      $this->commitFiles($deploy_dir, $project);
      $result['messages'][] = t('Changes committed to Git.');

      // 6. Push to remote if configured.
      $remote_url = $options['remote_url'] ?? '';
      $branch = $options['branch'] ?? 'main';

      if (!empty($remote_url)) {
        $this->pushToRemote($deploy_dir, $remote_url, $branch);
        $result['messages'][] = t('Pushed to remote: @url', ['@url' => $remote_url]);
        $result['remote_url'] = $remote_url;
      }
      else {
        $result['messages'][] = t('No remote configured. Project staged locally. Use the CI/CD pipeline template to set up automated deployment.');
      }

      // 7. Update project entity.
      $domain = $project->getDomain();
      $url = $domain ? 'https://' . $domain->getHostname() : '';
      $this->updateProjectEntity($project, $deploy_dir, $remote_url ?: $deploy_dir);

      $result['status'] = 'success';
      $result['deploy_url'] = $url;

      $this->logger->notice('Project @name prepared for deployment', [
        '@name' => $project->getName(),
      ]);

    }
    catch (\Exception $e) {
      $result['messages'][] = t('Deployment failed: @message', ['@message' => $e->getMessage()]);
      $this->logger->error('Project deployment failed: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $result;
  }

  /**
   * Prepare the deployment staging directory.
   */
  protected function prepareDeploymentDirectory(Project $project) {
    $slug = $project->getSlug();
    $deploy_dir = $this->deployPath . '/' . $slug;

    // Create base deploy directory if it doesn't exist.
    if (!is_dir($this->deployPath)) {
      mkdir($this->deployPath, 0755, TRUE);
    }

    // Create deployment directory.
    if (!is_dir($deploy_dir)) {
      mkdir($deploy_dir, 0755, TRUE);
    }

    return $deploy_dir;
  }

  /**
   * Sync exported files to deployment staging directory.
   */
  protected function syncExportedFiles(Project $project, string $deploy_dir) {
    $export_service = \Drupal::service('startupfactory_project.export');
    $export_dir = $export_service->getExportPath($project);

    if (!is_dir($export_dir)) {
      throw new \RuntimeException('Export directory not found: ' . $export_dir);
    }

    // Copy files using PHP (rsync not available in container).
    $this->copyDirectory($export_dir, $deploy_dir);
  }

  /**
   * Recursively copy a directory.
   */
  protected function copyDirectory(string $source, string $destination) {
    if (!is_dir($destination)) {
      mkdir($destination, 0755, TRUE);
    }

    $files = scandir($source);
    foreach ($files as $file) {
      if ($file === '.' || $file === '..') {
        continue;
      }

      $source_path = $source . '/' . $file;
      $dest_path = $destination . '/' . $file;

      if (is_dir($source_path)) {
        $this->copyDirectory($source_path, $dest_path);
      }
      else {
        copy($source_path, $dest_path);
      }
    }
  }

  /**
   * Configure environment for VPS deployment.
   */
  protected function configureEnvironment(Project $project, string $deploy_dir, array $options) {
    $domain = $project->getDomain();
    $hostname = $domain ? $domain->getHostname() : $project->getSlug() . '.localhost';

    // Generate .env file.
    $env_content = [
      '# Database',
      'DB_PASSWORD=' . ($options['db_password'] ?? 'drupal'),
      'MYSQL_ROOT_PASSWORD=' . ($options['mysql_root_password'] ?? 'root'),
      '',
      '# Domain',
      'VIRTUAL_HOST=' . $hostname,
      'LETSENCRYPT_HOST=' . $hostname,
      '',
      '# Drupal',
      'DRUPAL_DATABASE_HOST=mariadb',
      'DRUPAL_DATABASE_NAME=drupal',
      'DRUPAL_DATABASE_USER=drupal',
      '',
      '# Redis',
      'DRUPAL_REDIS_HOST=redis',
      'DRUPAL_REDIS_PORT=6379',
    ];

    file_put_contents($deploy_dir . '/.env', implode("\n", $env_content));

    // Add .env to .gitignore if not present.
    $gitignore = $deploy_dir . '/.gitignore';
    if (!file_exists($gitignore)) {
      file_put_contents($gitignore, ".env\n");
    }
  }

  /**
   * Initialize Git repository.
   */
  protected function initGitRepo(string $deploy_dir) {
    // Check if already a git repo.
    if (is_dir($deploy_dir . '/.git')) {
      return;
    }

    $commands = [
      ['git', 'init'],
      ['git', 'config', 'user.email', 'deploy@startupfactory.dev'],
      ['git', 'config', 'user.name', 'Startup Factory Deploy'],
    ];

    foreach ($commands as $command) {
      $process = new Process($command);
      $process->setWorkingDirectory($deploy_dir);
      $process->setTimeout(30);
      $process->run();

      if (!$process->isSuccessful()) {
        throw new \RuntimeException('Git init failed: ' . $process->getErrorOutput());
      }
    }
  }

  /**
   * Commit all files.
   */
  protected function commitFiles(string $deploy_dir, Project $project) {
    $commands = [
      ['git', 'add', '-A'],
      ['git', 'commit', '-m', 'Deploy ' . $project->getName() . ' (' . date('Y-m-d H:i:s') . ')'],
    ];

    foreach ($commands as $command) {
      $process = new Process($command);
      $process->setWorkingDirectory($deploy_dir);
      $process->setTimeout(60);
      $process->run();

      // Ignore "nothing to commit" error.
      if (!$process->isSuccessful() && strpos($process->getOutput(), 'nothing to commit') === FALSE) {
        throw new \RuntimeException('Git commit failed: ' . $process->getErrorOutput());
      }
    }
  }

  /**
   * Push to remote Git repository.
   */
  protected function pushToRemote(string $deploy_dir, string $remote_url, string $branch) {
    // Add remote if not exists.
    $process = new Process(['git', 'remote', 'get-url', 'origin']);
    $process->setWorkingDirectory($deploy_dir);
    $process->run();

    if (!$process->isSuccessful()) {
      // Add remote.
      $process = new Process(['git', 'remote', 'add', 'origin', $remote_url]);
      $process->setWorkingDirectory($deploy_dir);
      $process->setTimeout(30);
      $process->run();

      if (!$process->isSuccessful()) {
        throw new \RuntimeException('Failed to add remote: ' . $process->getErrorOutput());
      }
    }

    // Push to remote.
    $process = new Process(['git', 'push', '-u', 'origin', $branch]);
    $process->setWorkingDirectory($deploy_dir);
    $process->setTimeout(120);
    $process->run();

    if (!$process->isSuccessful()) {
      throw new \RuntimeException('Failed to push to remote: ' . $process->getErrorOutput());
    }
  }

  /**
   * Update project entity with deployment info.
   */
  protected function updateProjectEntity(Project $project, string $deploy_dir, string $remote_url) {
    $project->set('git_repo_url', [
      'uri' => $remote_url,
      'title' => 'Deployed: ' . $project->getSlug(),
    ]);
    $project->save();
  }

  /**
   * Get deployment path for a project.
   */
  public function getDeploymentPath(Project $project) {
    return $this->deployPath . '/' . $project->getSlug();
  }

  /**
   * Check if a project is deployed.
   */
  public function isDeployed(Project $project) {
    $deploy_dir = $this->getDeploymentPath($project);
    return is_dir($deploy_dir) && is_dir($deploy_dir . '/.git');
  }

  /**
   * Get deployment status.
   */
  public function getDeploymentStatus(Project $project) {
    $deploy_dir = $this->getDeploymentPath($project);

    if (!is_dir($deploy_dir)) {
      return 'not_deployed';
    }

    if (!is_dir($deploy_dir . '/.git')) {
      return 'not_initialized';
    }

    return 'ready';
  }

}
