<?php

namespace Drupal\startupfactory_project\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines the list builder for the Project entity.
 */
class ProjectListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Project Name');
    $header['slug'] = $this->t('Slug');
    $header['type'] = $this->t('Type');
    $header['owner'] = $this->t('Owner');
    $header['status'] = $this->t('Status');
    $header['domain'] = $this->t('Domain');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\startupfactory_project\Entity\Project */
    $row['name'] = Link::createFromRoute($entity->getName(), 'entity.project.canonical', ['project' => $entity->id()]);
    $row['slug'] = $entity->getSlug();
    $row['type'] = $entity->getProjectType();
    $row['owner'] = $entity->getOwner() ? $entity->getOwner()->getDisplayName() : $this->t('N/A');
    $row['status'] = $entity->isPublished() ? $this->t('Published') : $this->t('Unpublished');

    $domain = $entity->getDomain();
    $row['domain'] = $domain ? $domain->getHostname() : $this->t('Not assigned');

    return $row + parent::buildRow($entity);
  }

}
