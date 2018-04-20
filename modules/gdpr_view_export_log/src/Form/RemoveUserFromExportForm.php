<?php

namespace Drupal\gdpr_view_export_log\Form;

use Drupal\Console\Bootstrap\Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gdpr_view_export_log\Entity\ExportAudit;

/**
 * Form for removing a user from an export log.
 *
 * @package Drupal\gdpr_view_export_log\Form
 */
class RemoveUserFromExportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gdpr_export_log_remove_user_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL, $user_id = NULL) {
    $form['note'] = [
      '#markup' => $this->t('Only remove a user from this export if you have actually removed all their information from the export on the target machine and any copies'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => 'Remove',
      ],
    ];

    $form['id'] = [
      '#type' => 'hidden',
      '#default_value' => $id,
    ];

    $form['user_id'] = [
      '#type' => 'hidden',
      '#default_value' => $user_id,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $id_to_remove = $form_state->getValue('id');
    $audit_entry = ExportAudit::load($form_state->getValue('id'));

    foreach ($audit_entry->get('user_ids') as $index => $field) {
      if ($field->value == $id_to_remove) {
        $index_to_remove = $index;
        break;
      }
    }

    if (isset($index_to_remove)) {
      $audit_entry->get('user_ids')->removeItem($index_to_remove);
      $audit_entry->save();
    }

    \Drupal::messenger()->addMessage($this->t('User removed'));
    $form_state->setRedirect('gdpr_view_export_log.view_users', ['id' => $audit_entry->id()]);
  }
}