<?php


namespace Drupal\gdpr_fields\Form;


use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class GdprFieldFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gdpr_fields_field_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $filter_value = \Drupal::routeMatch()->getParameter('mode') == 'all' ? 1 : 0;

    $form['filter'] = [
      '#type' => 'checkbox',
      '#title' => 'Include Not Configured',
      '#default_value' => $filter_value,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
      '#button_type' => 'primary',
      '#name' => 'Apply',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mode = $form_state->getValue('filter') == '1' ? 'all' : 'configured';
    $form_state->setRedirect('gdpr_fields.fields_list', ['mode' => $mode]);
  }
}