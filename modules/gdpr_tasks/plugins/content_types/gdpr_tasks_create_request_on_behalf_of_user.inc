<?php

/**
 * @file
 * CTools content for request task on behalf of user content plugin.
 */

/**
 * Plugin definition.
 */
$plugin = array(
  'title' => t('GDPR Task request form'),
  'content_types' => 'gdpr_tasks_create_request_on_behalf_of_user',
  'single' => TRUE,
  'render callback' => 'gdpr_tasks_create_request_on_behalf_of_user_render',
  'description' => t('Show a form to request GDPR tasks.'),
  'required context' => new ctools_context_required(t('User'), 'entity:user'),
  'edit form' => 'gdpr_tasks_create_request_on_behalf_of_user_edit_form',
  'admin title' => 'gdpr_tasks_create_request_on_behalf_of_user_admin_title',
  'category' => array(t('GDPR'), 0),
);

/**
 * Render the User GDPR request form.
 *
 * @param string $subtype
 *   Plugin subtype name.
 * @param array $conf
 *   Configuration as done at admin time.
 * @param array $args
 *   Plugin arguments.
 * @param object $context
 *   Context - in this case we don't have any.
 *
 * @return object
 *   An object with at least title and content members
 */
function gdpr_tasks_create_request_on_behalf_of_user_render($subtype, array $conf, array $args, $context) {
  $block = new stdClass();
  $block->title = t('Request task on user behalf');
  $block->content = '';

  $user = $context->data;

  if (!empty($user)) {
    // Get hold of the form.
    $form = drupal_get_form('gdpr_tasks_create_request_on_behalf_of_user_form', $user, $conf);
    $block->content = drupal_render($form);
  }

  return $block;
}

/**
 * Form callback.
 */
function gdpr_tasks_create_request_on_behalf_of_user_form($form, &$form_state, $user, $conf = NULL) {
  form_load_include($form_state, 'inc', 'gdpr_tasks', 'plugins/content_types/gdpr_tasks_create_request_on_behalf_of_user');

  $form_state['#user'] = $user;
  $form['notes'] = array(
    '#type' => 'textarea',
    '#title' => t('Notes'),
    '#description' => t('Enter the reason for creating this request.'),
  );

  $form['actions'] = array('#type' => 'actions');
  $form['actions']['export'] = array(
    '#type' => 'submit',
    '#submit' => array('gdpr_tasks_create_request_on_behalf_of_user_form_export_submit'),
    '#value' => t('Request data export'),
    '#weight' => 40,
  );

  $form['actions']['removal'] = array(
    '#type' => 'submit',
    '#submit' => array('gdpr_tasks_create_request_on_behalf_of_user_form_removal_submit'),
    '#value' => t('Request data removal'),
    '#weight' => 40,
  );

  return $form;
}

/**
 * Submit handler for removal.
 */
function gdpr_tasks_create_request_on_behalf_of_user_form_removal_submit(&$form, &$form_state) {
  // Make sure we stop it redirecting anywhere it shouldn't.
  unset($form_state['redirect']);
  $options = array('query' => drupal_get_destination());
  $path = 'user/' . $form_state['#user']->uid . '/gdpr/requests/gdpr_remove/add';
  drupal_goto($path, $options);
}

/**
 * Submit handler for export.
 */
function gdpr_tasks_create_request_on_behalf_of_user_form_export_submit(&$form, &$form_state) {
  // Make sure we stop it redirecting anywhere it shouldn't.
  unset($form_state['redirect']);
  $options = array('query' => drupal_get_destination());
  $path = 'user/' . $form_state['#user']->uid . '/gdpr/requests/gdpr_sar/add';
  drupal_goto($path, $options);
}

/**
 * Config form.
 */
function gdpr_tasks_create_request_on_behalf_of_user_edit_form($form, &$form_state) {
  return $form;
}

/**
 * Submit handler for config form.
 */
function gdpr_tasks_create_request_on_behalf_of_user_edit_form_submit(&$form, &$form_state) {
  foreach (element_children($form) as $key) {
    if (!empty($form_state['values'][$key])) {
      $form_state['conf'][$key] = $form_state['values'][$key];
    }
  }
}

/**
 * Title callback.
 */
function gdpr_tasks_create_request_on_behalf_of_user_admin_title($subtype, $conf, $context = NULL) {
  if ($conf['override_title'] && !empty($conf['override_title_text'])) {
    $output = format_string('"@context" !title', array(
      '@context' => $context->identifier,
      '!title' => filter_xss_admin($conf['override_title_text']),
    ));
  }
  else {
    $output = t('"@context" Request task on user behalf', array(
      '@context' => $context->identifier,
    ));
  }
  return $output;
}
