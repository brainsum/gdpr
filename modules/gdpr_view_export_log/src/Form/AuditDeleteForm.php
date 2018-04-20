<?php

namespace Drupal\gdpr_view_export_log\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for deleting an export audit.
 *
 * @package Drupal\gdpr_view_export_log\Form
 */
class AuditDeleteForm extends ContentEntityDeleteForm {

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['description']['#markup'] =
      $this->t('Please only remove this export log if you have completely destroyed the export on the target computer and any copies of it. Are you sure you want to continue?');
    return $form;
  }

}