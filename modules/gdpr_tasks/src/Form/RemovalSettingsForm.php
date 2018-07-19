<?php

namespace Drupal\gdpr_tasks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for GDPR Removal task.
 *
 * @package Drupal\gdpr_tasks\Form
 */
class RemovalSettingsForm extends ConfigFormBase {

  const CONFIG_KEY = 'gdpr_tasks.settings';
  const EXPORT_DIRECTORY = 'export_directory';

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [self::CONFIG_KEY];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gdpr_tasks_removal_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Export Directory'),
      '#description' => $this->t('Specifies the path to the directory where Right to be Forgotten tasks are exported after being processed'),
      '#required' => TRUE,
      '#default_value' => $this->config(self::CONFIG_KEY)
        ->get(self::EXPORT_DIRECTORY),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if ($form_state->hasValue('directory')) {
      $directory = $form_state->getValue('directory');
      if (empty($directory)) {
        $form_state->setErrorByName('directory', $this->t('The directory is required.'));
        return;
      }
      if (!\file_prepare_directory($directory)) {
        $form_state->setErrorByName('directory', $this->t("The directory does not exist or it's not writable."));
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(self::CONFIG_KEY)
      ->set(self::EXPORT_DIRECTORY, $form_state->getValue('directory'))
      ->save();

    $this->messenger()->addStatus('Changes saved.');
  }

}
