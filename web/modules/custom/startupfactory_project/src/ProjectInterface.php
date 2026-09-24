<?php

namespace Drupal\startupfactory_project;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for the Project entity.
 */
interface ProjectInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Gets the project name.
   */
  public function getName();

  /**
   * Sets the project name.
   */
  public function setName($name);

  /**
   * Gets the project slug.
   */
  public function getSlug();

  /**
   * Sets the project slug.
   */
  public function setSlug($slug);

  /**
   * Gets the project type.
   */
  public function getProjectType();

  /**
   * Sets the project type.
   */
  public function setProjectType($type);

  /**
   * Returns whether the project is public.
   */
  public function isPublic();

  /**
   * Sets the public visibility.
   */
  public function setPublic($public);

  /**
   * Gets the domain entity.
   */
  public function getDomain();

  /**
   * Sets the domain entity.
   */
  public function setDomain($domain);

  /**
   * Gets the git repository URL.
   */
  public function getGitRepoUrl();

  /**
   * Sets the git repository URL.
   */
  public function setGitRepoUrl($url);

  /**
   * Gets the ragchat shop node ID.
   */
  public function getRagchatShopId();

  /**
   * Sets the ragchat shop node ID.
   */
  public function setRagchatShopId($shop_id);

}
