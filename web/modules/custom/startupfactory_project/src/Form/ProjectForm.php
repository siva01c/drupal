<?php

namespace Drupal\startupfactory_project\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the Project add/edit form.
 */
class ProjectForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->domainNegotiator = $container->get('domain.negotiator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $entity = $this->entity;

    // Auto-generate slug from name on add form.
    if ($entity->isNew()) {
      $form['name']['#attributes']['class'][] = 'js-project-name';
      $form['slug']['#attributes']['class'][] = 'js-project-slug';
      $form['slug']['#description'] = $this->t('Auto-generated from name. You can override it.');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $status = parent::save($form, $form_state);

    $op = $status === SAVED_NEW ? 'created' : 'updated';
    $this->messenger()->addStatus($this->t('Project @label has been @op.', [
      '@label' => $entity->label(),
      '@op' => $op,
    ]));

    $form_state->setRedirect('entity.project.canonical', ['project' => $entity->id()]);
  }

}
