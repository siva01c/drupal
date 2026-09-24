<?php

namespace Drupal\startupfactory_project\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\link\LinkItemInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\startupfactory_project\ProjectInterface;

/**
 * Defines the Project entity.
 *
 * @ContentEntityType(
 *   id = "project",
 *   label = @Translation("Project"),
 *   label_collection = @Translation("Projects"),
 *   label_singular = @Translation("project"),
 *   label_plural = @Translation("projects"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\startupfactory_project\Entity\ProjectListBuilder",
 *     "form" = {
 *       "default" = "Drupal\startupfactory_project\Form\ProjectForm",
 *       "add" = "Drupal\startupfactory_project\Form\ProjectForm",
 *       "edit" = "Drupal\startupfactory_project\Form\ProjectForm",
 *       "delete" = "Drupal\startupfactory_project\Form\ProjectDeleteForm",
 *     },
 *     "access" = "Drupal\startupfactory_project\Entity\ProjectAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "project",
 *   translatable = FALSE,
 *   admin_permission = "administer project entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "canonical" = "/project/{project}",
 *     "add-form" = "/project/add",
 *     "edit-form" = "/project/{project}/edit",
 *     "delete-form" = "/project/{project}/delete",
 *     "collection" = "/admin/content/projects",
 *   },
 *   field_ui_base_route = "entity.project.collection",
 * )
 */
class Project extends ContentEntityBase implements ProjectInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Project Name'))
      ->setDescription(t('The display name of the project.'))
      ->setSetting('max_length', 255)
      ->setSetting('text_processing', 0)
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['slug'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Slug'))
      ->setDescription(t('URL-safe machine name, used as domain ID. Auto-generated from name.'))
      ->setSetting('max_length', 128)
      ->setSetting('text_processing', 0)
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -9,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Project Type'))
      ->setDescription(t('The type of project.'))
      ->setSetting('allowed_values', [
        'web' => 'Web App',
        'eshop' => 'E-Shop',
        'chatbot' => 'AI Chatbot',
        'saas' => 'SaaS Platform',
      ])
      ->setDefaultValue('web')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -8,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['visibility'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Public'))
      ->setDescription(t('Whether this project is publicly visible.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -7,
        'settings' => [
          'label' => t('Public — visible in the project catalog'),
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['domain'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Domain'))
      ->setDescription(t('The Domain entity for this project tenant.'))
      ->setSetting('target_type', 'domain')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['git_repo_url'] = BaseFieldDefinition::create('link')
      ->setLabel(t('Git Repository URL'))
      ->setDescription(t('URL of the exported Git repository for this project.'))
      ->setSetting('max_length', 2048)
      ->setSetting('link_type', LinkItemInterface::LINK_GENERIC)
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'link_default',
        'weight' => -5,
        'settings' => [
          'placeholder_title' => 'https://github.com/user/repo',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Published'))
      ->setDescription(t('Whether the project is published.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -4,
        'settings' => [
          'label' => t('Published'),
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['ragchat_shop_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('RagChat Shop ID'))
      ->setDescription(t('The node ID of the corresponding shop in ragchat.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time the project was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time the project was last updated.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSlug() {
    return $this->get('slug')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSlug($slug) {
    $this->set('slug', $slug);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectType() {
    return $this->get('type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setProjectType($type) {
    $this->set('type', $type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublic() {
    return (bool) $this->get('visibility')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPublic($public) {
    $this->set('visibility', (int) $public);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDomain() {
    return $this->get('domain')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setDomain($domain) {
    $this->set('domain', $domain);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGitRepoUrl() {
    return $this->get('git_repo_url')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setGitRepoUrl($url) {
    $this->set('git_repo_url', $url);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRagchatShopId() {
    return (int) $this->get('ragchat_shop_id')->value ?: 0;
  }

  /**
   * {@inheritdoc}
   */
  public function setRagchatShopId($shop_id) {
    $this->set('ragchat_shop_id', (int) $shop_id);
    return $this;
  }

}
