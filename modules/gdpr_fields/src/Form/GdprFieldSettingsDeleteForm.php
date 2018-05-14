<?php

namespace Drupal\gdpr_fields\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;

/**
 * Delete confirmation form for GDPR field settings.
 */
class GdprFieldSettingsDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to remove the GDPR settings from this field?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('gdpr_fields.fields_list');
  }

}
