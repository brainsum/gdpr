<?php

namespace Drupal\gdpr_tasks\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class TaskTypeForm.
 */
class TaskTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $gdpr_task_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $gdpr_task_type->label(),
      '#description' => $this->t("Label for the Task type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $gdpr_task_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\gdpr_tasks\Entity\TaskType::load',
      ],
      '#disabled' => !$gdpr_task_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $gdpr_task_type = $this->entity;
    $status = $gdpr_task_type->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Task type.', [
          '%label' => $gdpr_task_type->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Task type.', [
          '%label' => $gdpr_task_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($gdpr_task_type->toUrl('collection'));
  }

}
