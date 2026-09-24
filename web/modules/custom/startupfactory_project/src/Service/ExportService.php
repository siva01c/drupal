<?php

namespace Drupal\startupfactory_project\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\startupfactory_project\Entity\Project;
use Symfony\Component\Process\Process;

/**
 * Service for exporting projects to Git repositories.
 */
class ExportService {

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
   * Base path for exports.
   *
   * @var string
   */
  protected $exportPath;

  /**
   * Constructs a new ExportService.
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

    // Use configured export path or default to a temp directory.
    $this->exportPath = $settings->get('startupfactory_export_path', '/tmp/startupfactory_exports');
  }

  /**
   * Export a project to a local Git repository.
   *
   * @param \Drupal\startupfactory_project\Entity\Project $project
   *   The project entity to export.
   * @param array $options
   *   Export options.
   *
   * @return array
   *   Export result with status, path, and messages.
   */
  public function export(Project $project, array $options = []) {
    $result = [
      'status' => 'error',
      'path' => '',
      'messages' => [],
      'git_url' => '',
    ];

    try {
      // 1. Prepare export directory.
      $export_dir = $this->prepareExportDirectory($project);
      $result['path'] = $export_dir;
      $result['messages'][] = t('Export directory prepared: @path', ['@path' => $export_dir]);

      // 2. Generate template files.
      $this->generateTemplateFiles($project, $export_dir, $options);
      $result['messages'][] = t('Template files generated.');

      // 3. Export Drupal config.
      $this->exportDrupalConfig($export_dir);
      $result['messages'][] = t('Drupal configuration exported.');

      // 4. Generate custom module stub.
      $this->generateCustomModule($project, $export_dir);
      $result['messages'][] = t('Custom module stub generated.');

      // 5. Initialize Git repository.
      $this->initGitRepo($export_dir);
      $result['messages'][] = t('Git repository initialized.');

      // 6. Commit all files.
      $this->commitFiles($export_dir, $project);
      $result['messages'][] = t('Files committed to Git.');

      // 7. Update project entity with export path.
      $this->updateProjectEntity($project, $export_dir);

      $result['status'] = 'success';
      $result['git_url'] = $export_dir;

      $this->logger->notice('Project @name exported successfully to @path', [
        '@name' => $project->getName(),
        '@path' => $export_dir,
      ]);

    }
    catch (\Exception $e) {
      $result['messages'][] = t('Export failed: @message', ['@message' => $e->getMessage()]);
      $this->logger->error('Project export failed: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $result;
  }

  /**
   * Prepare the export directory.
   */
  protected function prepareExportDirectory(Project $project) {
    $slug = $project->getSlug();
    $export_dir = $this->exportPath . '/' . $slug;

    // Create base export directory if it doesn't exist.
    if (!is_dir($this->exportPath)) {
      mkdir($this->exportPath, 0755, TRUE);
    }

    // Remove existing export directory.
    if (is_dir($export_dir)) {
      $this->removeDirectory($export_dir);
    }

    // Create fresh export directory.
    mkdir($export_dir, 0755, TRUE);

    return $export_dir;
  }

  /**
   * Generate template files based on project type.
   */
  protected function generateTemplateFiles(Project $project, string $export_dir, array $options) {
    $template_dir = $this->moduleHandler->getModule('startupfactory_project')->getPath() . '/templates';
    $project_type = $project->getProjectType();
    $project_name = $project->getName();

    // Copy base templates.
    $this->copyTemplateFiles($template_dir . '/base', $export_dir, [
      'project_name' => $project_name,
      'project_slug' => $project->getSlug(),
      'project_type' => $project_type,
      'project_type_label' => $this->getProjectTypeLabel($project_type),
    ]);

    // Copy project-type specific templates.
    $this->copyTemplateFiles($template_dir . '/' . $project_type, $export_dir, [
      'project_name' => $project_name,
      'project_slug' => $project->getSlug(),
    ]);

    // Copy CI/CD templates based on options.
    $ci_provider = $options['ci_provider'] ?? 'github';
    $ci_dir = $template_dir . '/' . $ci_provider;
    if (is_dir($ci_dir)) {
      $this->copyTemplateFiles($ci_dir, $export_dir, [
        'project_name' => $project_name,
        'project_slug' => $project->getSlug(),
      ]);
    }
  }

  /**
   * Copy template files with variable substitution.
   */
  protected function copyTemplateFiles(string $source_dir, string $dest_dir, array $variables) {
    if (!is_dir($source_dir)) {
      return;
    }

    $files = scandir($source_dir);
    foreach ($files as $file) {
      if ($file === '.' || $file === '..') {
        continue;
      }

      $source = $source_dir . '/' . $file;
      $dest = $dest_dir . '/' . $file;

      if (is_dir($source)) {
        mkdir($dest, 0755, TRUE);
        $this->copyTemplateFiles($source, $dest, $variables);
      }
      else {
        $content = file_get_contents($source);
        $content = $this->replaceVariables($content, $variables);
        file_put_contents($dest, $content);
      }
    }
  }

  /**
   * Replace variables in template content.
   */
  protected function replaceVariables(string $content, array $variables) {
    foreach ($variables as $key => $value) {
      $content = str_replace('{{' . $key . '}}', $value, $content);
    }
    return $content;
  }

  /**
   * Export Drupal configuration.
   */
  protected function exportDrupalConfig(string $export_dir) {
    // Create config directory in export.
    $config_dir = $export_dir . '/config/sync';
    mkdir($config_dir, 0755, TRUE);

    // Export config using Drush.
    $drush = '/var/www/html/drupal/bin/drush';
    $process = new Process([
      $drush, 'config:export', '--destination=' . $config_dir, '-y',
    ]);
    $process->setTimeout(300);
    $process->run();

    if (!$process->isSuccessful()) {
      throw new \RuntimeException('Config export failed: ' . $process->getErrorOutput());
    }
  }

  /**
   * Generate custom module stub.
   */
  protected function generateCustomModule(Project $project, string $export_dir) {
    $module_dir = $export_dir . '/web/modules/custom/' . $project->getSlug();
    mkdir($module_dir, 0755, TRUE);
    mkdir($module_dir . '/src', 0755, TRUE);

    // Generate .info.yml.
    $info_content = "name: '" . $project->getName() . "'\ntype: module\ndescription: 'Custom module for " . $project->getName() . "'\ncore_version_requirement: ^11\npackage: 'Custom'\n";
    file_put_contents($module_dir . '/' . $project->getSlug() . '.info.yml', $info_content);

    // Generate .module stub.
    $module_content = "<?php\n\n/**\n * @file\n * Custom module for " . $project->getName() . ".\n */\n";
    file_put_contents($module_dir . '/' . $project->getSlug() . '.module', $module_content);
  }

  /**
   * Initialize Git repository.
   */
  protected function initGitRepo(string $export_dir) {
    $commands = [
      ['git', 'init'],
      ['git', 'config', 'user.email', 'export@startupfactory.dev'],
      ['git', 'config', 'user.name', 'Startup Factory Export'],
    ];

    foreach ($commands as $command) {
      $process = new Process($command);
      $process->setWorkingDirectory($export_dir);
      $process->setTimeout(30);
      $process->run();

      if (!$process->isSuccessful()) {
        throw new \RuntimeException('Git command failed: ' . $process->getErrorOutput());
      }
    }
  }

  /**
   * Commit all files.
   */
  protected function commitFiles(string $export_dir, Project $project) {
    $commands = [
      ['git', 'add', '-A'],
      ['git', 'commit', '-m', 'Initial export of ' . $project->getName()],
    ];

    foreach ($commands as $command) {
      $process = new Process($command);
      $process->setWorkingDirectory($export_dir);
      $process->setTimeout(60);
      $process->run();

      if (!$process->isSuccessful()) {
        throw new \RuntimeException('Git commit failed: ' . $process->getErrorOutput());
      }
    }
  }

  /**
   * Update project entity with export path.
   */
  protected function updateProjectEntity(Project $project, string $export_dir) {
    $project->set('git_repo_url', [
      'uri' => $export_dir,
      'title' => 'Local export: ' . $project->getSlug(),
    ]);
    $project->save();
  }

  /**
   * Get project type label.
   */
  protected function getProjectTypeLabel(string $type) {
    $labels = [
      'web' => 'Web Application',
      'eshop' => 'E-Commerce Shop',
      'chatbot' => 'AI Chatbot',
      'saas' => 'SaaS Platform',
    ];
    return $labels[$type] ?? ucfirst($type);
  }

  /**
   * Recursively remove a directory.
   */
  protected function removeDirectory(string $path) {
    if (is_dir($path)) {
      $files = array_diff(scandir($path), ['.', '..']);
      foreach ($files as $file) {
        $obj = $path . '/' . $file;
        if (is_dir($obj)) {
          $this->removeDirectory($obj);
        }
        else {
          unlink($obj);
        }
      }
      rmdir($path);
    }
  }

  /**
   * Get export path for a project.
   */
  public function getExportPath(Project $project) {
    return $this->exportPath . '/' . $project->getSlug();
  }

  /**
   * Check if a project has been exported.
   */
  public function isExported(Project $project) {
    $export_dir = $this->getExportPath($project);
    return is_dir($export_dir) && is_dir($export_dir . '/.git');
  }

}
