<?php

/**
 * @file
 * Admin pages file for the GDPR Consent module.
 */

/**
 * Form for managing a consent agreement entity.
 */
function gdpr_consent_agreement_form($form, &$form_state, GdprConsentAgreement $entity = NULL) {
  $form['title'] = array(
    '#title' => t('Title'),
    '#type' => 'textfield',
    '#default_value' => isset($entity->title) ? $entity->title : '',
    '#description' => t('Agreement Title'),
    '#required' => TRUE,
    '#weight' => -50,
  );
  $form['name'] = array(
    '#type' => 'machine_name',
    '#default_value' => isset($entity->name) ? $entity->name : '',
    '#disabled' => $entity->hasStatus(ENTITY_IN_CODE),
    '#machine_name' => array(
      'exists' => 'gdpr_consent_agreement_load_multiple_by_name',
      'source' => array('title'),
    ),
    '#description' => t('A unique machine-readable name for this agreement. It must only contain lowercase letters, numbers, and underscores.'),
    '#weight' => -50,
  );
  $form['agreement_type'] = array(
    '#title' => t('Agreement Type'),
    '#type' => 'select',
    '#options' => array(
      GDPR_CONSENT_TYPE_IMPLICIT => t('Implicit'),
      GDPR_CONSENT_TYPE_EXPLICIT => t('Explicit'),
    ),
    '#default_value' => isset($entity->agreement_type) ? $entity->agreement_type : '',
    '#description' => t('Whether consent is implicit or explicit. Set to "Explicit" if the user needs to explicitly agree, otherwise "Implicit'),
  );
  $form['description'] = array(
    '#title' => t('Description'),
    '#type' => 'textarea',
    '#default_value' => isset($entity->description) ? $entity->description : '',
    '#description' => t('Text displayed to the user on the form'),
  );
  $form['long_description'] = array(
    '#title' => t('Long Description'),
    '#type' => 'textarea',
    '#default_value' => isset($entity->long_description) ? $entity->long_description : '',
    '#description' => t('Text shown when the user clicks for more details'),
  );
  $form['notes'] = array(
    '#title' => t('Notes'),
    '#type' => 'textarea',
    '#default_value' => isset($entity->notes) ? $entity->notes : '',
    '#description' => t('This should contain the rationale behind the agreement.'),
  );
  $form['revision'] = array(
    '#type' => 'checkbox',
    '#title' => t('Create new revision'),
    '#default_value' => 1,
  );
  $form['log'] = array(
    '#type' => 'textarea',
    '#title' => t('Revision log message'),
    '#rows' => 4,
    '#default_value' => !empty($entity->log) ? $entity->log : '',
    '#description' => t('Briefly describe the changes you have made.'),
  );

  field_attach_form('gdpr_consent_agreement', $entity, $form, $form_state);

  $form['actions'] = array(
    '#type' => 'actions',
    'submit' => array(
      '#type' => 'submit',
      '#value' => isset($entity->id) ? t('Update consent agreement') : t('Save consent agreement'),
    ),
  );

  return $form;
}

/**
 * Submit handler for consent agreement entity form.
 */
function gdpr_consent_agreement_form_submit(&$form, &$form_state) {
  $entity = entity_ui_form_submit_build_entity($form, $form_state);
  $entity->save();
  drupal_set_message(t('@title agreement has been saved.', array('@title' => $entity->title)));
  $form_state['redirect'] = 'admin/config/gdpr/agreements';
}

/**
 * Generates an overview table of older revisions of a Consent Agreement.
 *
 * @param GdprConsentAgreement $agreement
 *   A consent agreement entity.
 *
 * @return array
 *   An array as expected by drupal_render().
 */
function gdpr_consent_agreement_revision_overview(GdprConsentAgreement $agreement) {
  drupal_set_title(t('Revisions for @title', array('@title' => $agreement->title)));

  $header = array(
    t('Revision'),
    array('data' => t('Operations'), 'colspan' => 2),
  );

  $revisions = gdpr_consent_revision_list($agreement);

  $rows = array();

  foreach ($revisions as $revision) {
    $row = array();
    $operations = array();

    if ($revision->current_revision > 0) {
      $row[] = array(
        'data' => t('!date by !username', array(
          '!date' => l(format_date($revision->timestamp, 'short'), "admin/config/gdpr/agreements/$agreement->id"),
          '!username' => theme('username', array('account' => $revision)),
        )) . (($revision->log != '') ? '<p class="revision-log">' . filter_xss($revision->log) . '</p>' : ''),
        'class' => array('revision-current'),
      );
      $operations[] = array(
        'data' => drupal_placeholder(t('current revision')),
        'class' => array('revision-current'),
        'colspan' => 2,
      );
    }
    else {
      $row[] = t('!date by !username', array(
        '!date' => l(format_date($revision->timestamp, 'short'), "admin/config/gdpr/agreements/$agreement->id/revisions/$revision->revision_id/view"),
        '!username' => theme('username', array('account' => $revision)),
      )) . (($revision->log != '') ? '<p class="revision-log">' . filter_xss($revision->log) . '</p>' : '');
      $operations[] = l(t('revert'), "admin/config/gdpr/agreements/$agreement->id/revisions/$revision->revision_id/revert");
      $operations[] = l(t('delete'), "admin/config/gdpr/agreements/$agreement->id/revisions/$revision->revision_id/delete");
    }
    $rows[] = array_merge($row, $operations);
  }

  $build['gdpr_consent_revisions_table'] = array(
    '#theme' => 'table',
    '#rows' => $rows,
    '#header' => $header,
  );

  return $build;
}

/**
 * Returns a list of all the existing revision numbers.
 *
 * @param GdprConsentAgreement $agreement
 *   The Consent Agreement entity.
 *
 * @return array
 *   An associative array keyed by revision number.
 */
function gdpr_consent_revision_list(GdprConsentAgreement $agreement) {
  $revisions = array();
  $result = db_query('SELECT r.revision_id, a.title, r.log, r.author_uid, a.revision_id AS current_revision, r.timestamp, u.name FROM {gdpr_consent_agreement_revision} r LEFT JOIN {gdpr_consent_agreement} a ON a.revision_id = r.revision_id INNER JOIN {users} u ON u.uid = r.author_uid WHERE r.id = :id ORDER BY r.revision_id DESC', array(':id' => $agreement->id));
  foreach ($result as $revision) {
    $revisions[$revision->revision_id] = $revision;
  }

  return $revisions;
}
