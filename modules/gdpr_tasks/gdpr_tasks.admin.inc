<?php

/**
 * @file
 * Administrative page and form callbacks for the GDPR Tasks module.
 */

/**
 * Edit form for task bundles.
 */
function gdpr_task_type_form($form, &$form_state, $bundle = array(), $op = 'edit') {

  // @todo Do we need to edit bundles?
  $form['message'] = array(
    '#type' => 'markup',
    '#markup' => 'Editing of GDPR Task types is not currently supported.'
  );

  return $form;
}

/**
 * Form callback for all task bundles.
 */
function gdpr_task_form($form, &$form_state) {
  $task = $form_state['task'];
  field_attach_form('gdpr_task', $task, $form, $form_state);

  if ($task->user_id == $task->requested_by) {
    $form['gdpr_tasks_notes']['#access'] = FALSE;
  }

  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
    '#weight' => 40,
  );

  return $form;
}

/**
 * Validate handler for all task bundles.
 */
function gdpr_task_form_validate($form, &$form_state) {
  $task = $form_state['task'];
  field_attach_validate('gdpr_task', $task);
}

/**
 * Submit handler for all task bundles.
 */
function gdpr_task_form_submit($form, &$form_state) {
  global $user;

  /* @var GDPRTask $task */
  $task = $form_state['task'];

  // General form submission.
  field_attach_submit('gdpr_task', $task, $form, $form_state);

  // Process and close the task.
  $task->processed_by = $user->uid;
  drupal_set_message(t('Task has been processed.'));
  $task->status = 'processed';
  $task->save();

  // Send confirmation email.
  gdpr_tasks_send_mail('task_processed', $task);
}

/**
 * Form callback for removal tasks.
 */
function gdpr_task_edit_gdpr_remove_form($form, &$form_state) {
  $task = $form_state['task'] = $form_state['build_info']['args'][0];
  $form = gdpr_task_form($form, $form_state);

  $header_table = array(
    'Name',
    'Data',
    'Notes',
    'Right to access',
  );
  $rows = gdpr_tasks_collect_rtf_data(user_load($task->user_id));

  $data_table = array(
    '#theme' => 'table',
    '#header' => $header_table,
    '#rows' => $rows,
    '#caption' => 'Export data',
  );

  $form['data'] = array(
    '#markup' => drupal_render($data_table),
  );

  $form['actions']['submit']['#value'] = t('Remove and Anonymise Data');
  $form['actions']['submit']['#name'] = 'remove';

  if ($task->status == 'closed') {
//    $form['actions']['#access'] = FALSE;
  }

  return $form;
}

/**
 * Form callback for export tasks.
 */
function gdpr_task_edit_gdpr_sar_form($form, &$form_state) {
  $task = $form_state['task'] = $form_state['build_info']['args'][0];
  $form = gdpr_task_form($form, $form_state);

  $plugins = ctools_export_load_object('gdpr_fields_field_data');
  $inc_label_map = array(
    'inc' => '[' . t('Include') . ']',
    'maybe' => '[' . t('Maybe') . ']',
  );

  foreach ($task->gdpr_tasks_sar_export_parts[LANGUAGE_NONE] as $part) {
    if (($handle = fopen(drupal_realpath($part['uri']), "r")) !== FALSE) {
      $row_type = 'plugin';
      $type = array();
      $header = array();
      $rows = array();
      while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        switch ($row_type) {
          case 'plugin':
            foreach ($data as $key => $plugin_id) {
              if (isset($plugins[$plugin_id])) {
                $plugin = $plugins[$plugin_id];
                $inc = $plugin->getSetting('gdpr_fields_rta');
              }

              $type[$key] = isset($inc) ? $inc_label_map[$inc] : '';
            }

            $row_type = 'header';
            break;
          case 'header':
            $header = $data;
            $rows[] = $type;
            $row_type = 'row';
            break;
          default:
            $rows[] = $data;
            break;
        }
      }
      fclose($handle);
    }

    $form[$part['filename']] = array(
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#caption' => t('File contents for @filename', array('@filename' =>  $part['filename'])),
    );
  }

  $form['actions']['submit']['#value'] = t('Process');
  $form['actions']['submit']['#name'] = 'export';


  $form['gdpr_tasks_manual_data']['#access'] = FALSE;
  if ($task->status == 'closed') {
    // Disable export field form element.
    $form['gdpr_tasks_sar_export']['#disabled'] = TRUE;
    $form['gdpr_tasks_sar_export_parts']['#access'] = FALSE;
    $form['gdpr_tasks_sar_export_assets']['#access'] = FALSE;
    $form['actions']['#access'] = FALSE;
  }
  else {
    $form['gdpr_tasks_sar_export']['#access'] = FALSE;
  }

  return $form;
}

/**
 * Validate handler for removal tasks.
 */
function gdpr_task_edit_gdpr_remove_form_validate($form, &$form_state) {
  gdpr_task_form_validate($form, $form_state);
}

/**
 * Validate handler for export tasks.
 */
function gdpr_task_edit_gdpr_sar_form_validate($form, &$form_state) {
  gdpr_task_form_validate($form, $form_state);

  // Make sure there are no two files with the same filename.
  if (!empty($form_state['values']['gdpr_tasks_sar_export_parts'])) {
    $files = array();
    // Check for filename duplicates.
    foreach ($form_state['values']['gdpr_tasks_sar_export_parts'][LANGUAGE_NONE] as $data) {
      $file = file_load($data['fid']);
      if (!isset($files[$file->filename])) {
        $files[$file->filename] = $file->filename;
      }
      else {
        form_set_error('gdpr_tasks_sar_export_parts', 'Please remove the old file when replacing with a new one.');
      }
    }
  }
}

/**
 * Submit handler for removal tasks.
 */
function gdpr_task_edit_gdpr_remove_form_submit($form, &$form_state) {
  $anonymizer = new Anonymizer();
  $task = $form_state['task'];
  $errors = $anonymizer->run($task);

  // Copy log to form_state.
  $form_state['values']['gdpr_tasks_removal_log'] = $task->gdpr_tasks_removal_log;

  if (empty($errors)) {
    gdpr_task_form_submit($form, $form_state);
  }
  else {
    dpm($errors);
    $form_state['rebuild'] = TRUE;
  }

}

/**
 * Submit handler for export tasks.
 */
function gdpr_task_edit_gdpr_sar_form_submit($form, &$form_state) {
  gdpr_task_form_submit($form, $form_state);

  // Process the export.
  /* @var GDPRTask $task */
  $task = $form_state['task'];
  $queue = \DrupalQueue::get('gdpr_tasks_process_gdpr_sar');
  $queue->createItem($task->identifier());
}

/**
 * Config form for automated emails for task requests.
 */
function gdpr_tasks_email_settings($form, &$form_state) {
  $form['gdpr_tasks_emails'] = array('#tree' => TRUE);
  $form['gdpr_tasks_emails']['emails'] = array(
    '#type' => 'vertical_tabs',
  );

  $emails = variable_get('gdpr_tasks_emails', array());
  $tokens = array('site', 'gdpr_task');

  $title = t('Request requested (by user)');
  $description = t('This email is sent when a task is requested by a user.');
  $form['gdpr_tasks_emails'] += gdpr_tasks_email_settings_subform('task_requested_self', $title, $description, $emails, $tokens);

  $title = t('Request requested (by staff)');
  $description = t('This email is sent when a task is requested by a staff member or administrator.');
  $form['gdpr_tasks_emails'] += gdpr_tasks_email_settings_subform('task_requested_other', $title, $description, $emails, $tokens);

  $title = t('Task processed');
  $description = t('This email is sent when a task has been prcessed by a staff member or administrator.');
  $form['gdpr_tasks_emails'] += gdpr_tasks_email_settings_subform('task_processed', $title, $description, $emails, $tokens);


  // Make sure anything not exposed is preserved.
  foreach ($emails as $key => $value) {
    if (!isset($form['gdpr_tasks_emails'][$key])) {
      $form['gdpr_tasks_emails'][$key] = array(
        '#type' => 'value',
        '#value' => $value,
      );
    }
  }

  $form['gdpr_tasks_emails_from'] = array(
    '#type' => 'textfield',
    '#title' => t('Email from address'),
    '#description' => t('Leave blank to use the site wide email address.'),
    '#default_value' => variable_get('gdpr_tasks_emails_from', NULL),
  );

  $form['#validate'][] = 'gdpr_tasks_email_settings_validate';
  $form['#submit'][] = 'gdpr_tasks_email_settings_submit';
  return system_settings_form($form);
}

/**
 * Validation handler for gdpr_tasks_email_settings().
 */
function gdpr_tasks_email_settings_validate(&$form, &$form_state) {
  foreach (element_children($form['gdpr_tasks_emails']) as $key) {
    // Skip our vertical tabs.
    if ($key == 'emails') {
      continue;
    }

    $element = $form['gdpr_tasks_emails'][$key];

    // If enabled, check we have our required values.
    $enabled = drupal_array_get_nested_value($form_state['values'], $element['enabled']['#parents']);
    if (!empty($enabled) && !empty($element['enabled']['#commerce_booking_team_email_dependents'])) {
      foreach ($element['enabled']['#commerce_booking_team_email_dependents'] as $array_parents) {
        // Get hold of the sub element we are requiring.
        $sub_element = drupal_array_get_nested_value($element, $array_parents);
        if (!$sub_element) {
          continue;
        }

        // Get hold of it's value and check it. Show an error if it's empty.
        $value = drupal_array_get_nested_value($form_state['values'], $sub_element['#parents']);
        if (empty($value)) {
          $error = t('%title is required if %set is enabled.', array(
            '%title' => $sub_element['#title'],
            '%set' => $element['#title'],
          ));
          form_error($sub_element, $error);
        }
      }
    }
  }
}

/**
 * Submission handler for gdpr_tasks_email_settings().
 */
function gdpr_tasks_email_settings_submit(&$form, &$form_state) {
  // Remove the vertical tabs hidden element.
  unset($form_state['values']['gdpr_tasks_emails']['emails']);
}

/**
 * Build the form elements for a particular
 *
 * @param $key string
 *   The form key for the element.
 * @param $title string
 *   The translated title for this email.
 * @param $description string
 *   The translated description for this email.
 * @param $settings array
 *   An array of settings for this email.
 * @param array $tokens
 *   An optional array of tokens which are supported for this email.
 *
 * @return array
 *   A fieldset form element array.
 */
function gdpr_tasks_email_settings_subform($key, $title, $description, $settings = array(), $tokens = array()) {
  // Pull the relevant key out of the settings.
  $settings = isset($settings[$key]) ? $settings[$key] : array();

  // Build our fieldset.
  $element = array(
    '#type' => 'fieldset',
    '#collapsible' => TRUE,
    '#group' => 'gdpr_tasks_emails][emails',
    '#title' => $title,
    '#description' => $description,
    '#commerce_booking_teams_email' => TRUE,
  );

  // Allow this email to be enabled/disabled.
  $element['enabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enable %title', array('%title' => $title)),
    '#default_value' => !empty($settings['enabled']),
    '#commerce_booking_team_email_dependents' => array(array('email', 'subject'), array('email', 'body', 'value')),
  );

  $element['email'] = array(
    '#type' => 'container',
    '#states' => array(
      'visible' => array(
        ":input[name=\"gdpr_tasks_emails[{$key}][enabled]\"]" => array('checked' => TRUE),
      ),
    ),
    '#parents' => array('gdpr_tasks_emails', $key),
  );

  // If we have tokens, output some help information.
  if (!empty($tokens)) {
    $element['email']['tokens'] = array(
      '#theme' => 'token_tree_link',
      '#token_types' => $tokens,
    );

  }

  // Subject line.
  $element['email']['subject'] = array(
    '#type' => 'textfield',
    '#title' => t('Subject'),
    '#default_value' => isset($settings['subject']) ? $settings['subject'] : NULL,
    '#maxlength' => 180,
    '#states' => array(
      'required' => array(
        ":input[name=\"gdpr_tasks_emails[{$key}][enabled]\"]" => array('checked' => TRUE),
      ),
    ),
  );

  // Body with format.
  $element['email']['body'] = array(
    '#type' => 'text_format',
    '#title' => t('Body'),
    '#rows' => 15,
    '#format' => isset($settings['body']['format']) ? $settings['body']['format'] : NULL,
    '#default_value' => isset($settings['body']['value']) ? $settings['body']['value'] : NULL,
    '#states' => array(
      'required' => array(
        ":input[name=\"gdpr_tasks_emails[{$key}][enabled]\"]" => array('checked' => TRUE),
      ),
    ),
  );

  // Return with our key.
  return array($key => $element);
}
