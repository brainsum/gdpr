<?php

/**
 * @file
 * CTools content for user edit form
 */

/**
 * Plugins are described by creating a $plugin array which will be used
 * by the system that includes this file.
 */
$plugin = array(
  'title' => t('GDPR Task request form'),
  'content_types' => 'gdpr_tasks_create_request_on_behalf_of_user',
  // 'single' means not to be subtyped.
  'single' => TRUE,
  // Name of a function which will render the block.
  'render callback' => 'gdpr_tasks_create_request_on_behalf_of_user_render',

  // Icon goes in the directory with the content type.
  'description' => t('Show a form to request GDPR tasks.'),
  'required context' => new ctools_context_required(t('User'), 'entity:user'),
  'edit form' => 'gdpr_tasks_create_request_on_behalf_of_user_edit_form',
  'admin title' => 'gdpr_tasks_create_request_on_behalf_of_user_admin_title',

  // presents a block which is used in the preview of the data.
  // Pn Panels this is the preview pane shown on the panels building page.
  'category' => array(t('GDPR'), 0),
);

/**
 * Render the User GDPR request form
 *
 * @param $subtype
 * @param $conf
 *   Configuration as done at admin time
 * @param $args
 * @param $context
 *   Context - in this case we don't have any
 *
 * @return
 *   An object with at least title and content members
 */
function gdpr_tasks_create_request_on_behalf_of_user_render($subtype, $conf, $args, $context) {
  $block = new stdClass();
  $block->title = t('Request task on user behalf');
  $block->content = '';

  $user = $form_state['#user'] = $context->data;

  if (!empty($user)) {
    // Get hold of the form
    $form = drupal_get_form('gdpr_tasks_create_request_on_behalf_of_user_form', $user, $conf);
    $block->content = drupal_render($form);
  }

  return $block;
}

/**
 * Form
 */
function gdpr_tasks_create_request_on_behalf_of_user_form($form, &$form_state, $user, $conf = NULL) {
//  form_load_include($form_state, 'inc', 'party', 'party.pages');
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
 * Submit Handler
 */
function gdpr_tasks_create_request_on_behalf_of_user_form_removal_submit(&$form, &$form_state) {
  // Make sure we stop it redirecting anywhere it shouldn't...
  unset($form_state['redirect']);
//  $conf = $form_state['build_info']['args'][1];
  $options = array('query' => drupal_get_destination());
  $path = 'user/' . $form_state['#user']->uid . '/gdpr/requests/gdpr_remove/add';
  drupal_goto($path, $options);
}

/**
 * Submit Handler
 */
function gdpr_tasks_create_request_on_behalf_of_user_form_export_submit(&$form, &$form_state) {
  // Make sure we stop it redirecting anywhere it shouldn't...
  unset($form_state['redirect']);
//  $conf = $form_state['build_info']['args'][1];
  $options = array('query' => drupal_get_destination());
  $path = 'user/' . $form_state['#user']->uid . '/gdpr/requests/gdpr_sar/add';
  drupal_goto($path, $options);
}

/**
 * Config Form
 */
function gdpr_tasks_create_request_on_behalf_of_user_edit_form($form, &$form_state) {
  return $form;
}

function gdpr_tasks_create_request_on_behalf_of_user_edit_form_submit(&$form, &$form_state) {
  foreach (element_children($form) as $key) {
    if (!empty($form_state['values'][$key])) {
      $form_state['conf'][$key] = $form_state['values'][$key];
    }
  }
}

/**
 * Title Callback
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
