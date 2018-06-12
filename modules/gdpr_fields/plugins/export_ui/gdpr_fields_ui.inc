<?php

/**
 * @file
 * Plugin definition for CTools Export UI integration.
 */

$plugin = array(
  'schema' => 'gdpr_fields_field_data',
  'access' => 'administer gdpr fields',

  'menu' => array(
    'menu prefix' => 'admin/config/gdpr',
    'menu item' => 'field-list',
    'menu title' => 'GDPR Field config',
    'menu description' => 'Find and configure GDPR field data.',
  ),

  'title singular' => t('field'),
  'title singular proper' => t('Field'),
  'title plural' => t('fields'),
  'title plural proper' => t('Fields'),

  'form' => array(
    'settings' => 'gdpr_fields_field_data_export_ui_form',
    'submit' => 'gdpr_fields_field_data_export_ui_form_submit',
  ),
  'handler' => 'gdpr_fields_ui',
  'strings' => array(
    'confirmation' => array(
      'revert' => array(
        'information' => t('This action will permanently remove any customizations made to this field.'),
        'success' => t('The field has been reverted.'),
      ),
    ),
  ),
);

/**
 * Define the preset add/edit form.
 */
function gdpr_fields_field_data_export_ui_form(&$form, &$form_state) {
  /* @var GDPRFieldData $field_data */
  $field_data = $form_state['item'];

  $form['field'] = array(
    '#type' => 'fieldset',
    '#title' => t('Field info'),
    '#tree' => TRUE,
  );

  $form['field']['entity_type'] = array(
    '#type' => 'textfield',
    '#title' => t('Entity type'),
    '#default_value' => $field_data->entity_type,
    '#disabled' => TRUE,
  );

  $form['field']['entity_bundle'] = array(
    '#type' => 'textfield',
    '#title' => t('Bundle'),
    '#default_value' => $field_data->entity_bundle,
    '#disabled' => TRUE,
  );

  $form['field']['property_name'] = array(
    '#type' => 'textfield',
    '#title' => t('Field name'),
    '#default_value' => $field_data->property_name,
    '#disabled' => TRUE,
  );

  $form['field']['label'] = array(
    '#type' => 'textfield',
    '#title' => t('Label'),
    '#default_value' => $field_data->getSetting('label', ''),
    '#disabled' => TRUE,
  );

  $form['field']['descriptions'] = array(
    '#type' => 'textfield',
    '#title' => t('Description'),
    '#default_value' => $field_data->getSetting('description', ''),
    '#disabled' => TRUE,
  );

  $form['settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Settings'),
    '#tree' => TRUE,
  );

  // @todo move this out of settings and set #states on settings fieldset.
  $form['settings']['gdpr_fields_enabled'] = array(
    '#type' => 'select',
    '#title' => t('GDPR Field'),
    '#description' => t('Is this a GDPR field'),
    '#options' => array(
      '' => t('Not defined'),
      '1' => t('Yes'),
      '0' => t('No'),
    ),
    '#default_value' => $field_data->getSetting('gdpr_fields_enabled', ''),
  );

  $all_properties = entity_get_property_info($field_data->entity_type);
  if (isset($all_properties['bundles'][$field_data->entity_bundle]['properties'][$field_data->property_name])) {
    $info = $all_properties['bundles'][$field_data->entity_bundle]['properties'][$field_data->property_name];
  }
  elseif (isset($all_properties['properties'][$field_data->property_name])) {
    $info = $all_properties['properties'][$field_data->property_name];
  }
  $type = isset($info['type']) ? $info['type'] : 'string';
  $is_list = substr($type, 0, 5) == 'list<';
  if ($is_list) {
    $type = entity_property_extract_innermost_type($type);
  }

  // Defaults.
  $form['settings']['gdpr_fields_owner'] = array(
    '#type' => 'value',
    '#value' => FALSE,
  );
  $form['settings']['gdpr_fields_no_follow'] = array(
    '#type' => 'value',
    '#value' => FALSE,
  );
  $form['settings']['gdpr_sars_filename'] = array(
    '#type' => 'value',
    '#value' => FALSE,
  );

  if (isset($info)) {
    if ($type != 'file' && ($entity_type = entity_get_info($type))) {
      // Ownership tracking.
      $form['settings']['gdpr_fields_owner'] = array(
        '#type' => 'checkbox',
        '#default_value' => $field_data->getSetting('gdpr_fields_owner', FALSE),
        '#title' => t('Field is owner'),
        '#description' => t('If checked, this entity will be included for any task including the %type this property references.', array(
          '%type' => $entity_type['label'],
        )),
        '#states' => array(
          'visible' => array(
            ':input[name="settings[gdpr_fields_enabled]"]' => array('value' => 1),
          ),
        ),
      );

      $form['settings']['gdpr_fields_no_follow'] = array(
        '#type' => 'checkbox',
        '#default_value' => $field_data->getSetting('gdpr_fields_no_follow', FALSE),
        '#title' => t('Do no follow this relationship'),
        '#description' => t('If checked, this relationship will not be followed when looking for additional personal information.'),
        '#states' => array(
          'visible' => array(
            ':input[name="settings[gdpr_fields_enabled]"]' => array('value' => 1),
          ),
        ),
      );

      // Target file.
      // @todo: Move to a form alter in gdpr_tasks.
      $form['settings']['gdpr_sars_filename'] = array(
        '#type' => 'textfield',
        '#title' => t('SARs filename'),
        '#description' => t('Specify which file this should be included in. The base user will go into %main.', array(
          '%main' => 'main.csv',
        )),
        // Default to the entity type.
        '#default_value' => $field_data->getSetting('gdpr_sars_filename', $type),
        '#field_suffix' => '.csv',
        '#size' => 20,
        '#required' => TRUE,
        '#states' => array(
          'visible' => array(
            ':input[name="settings[gdpr_fields_enabled]"]' => array('value' => 1),
            ':input[name="settings[gdpr_fields_no_follow]"]' => array('checked' => FALSE),
          ),
        ),
        '#weight' => 30,
      );
    }
  }

  $form['settings']['gdpr_fields_rta'] = array(
    '#type' => 'select',
    '#title' => t('Right to access'),
    '#options' => array(
      'inc' => t('Included'),
      'maybe' => t('Maybe'),
      'no' => t('Not'),
    ),
    '#default_value' => $field_data->getSetting('gdpr_fields_rta', 'no'),
    '#states' => array(
      'visible' => array(
        ':input[name="settings[gdpr_fields_enabled]"]' => array('value' => 1),
      ),
    ),
    '#weight' => 20,
  );

  $form['settings']['gdpr_fields_rtf'] = array(
    '#type' => 'select',
    '#title' => t('Right to be forgotten'),
    '#options' => array(
      'anonymise' => t('Anonymise'),
      'remove' => t('Remove'),
      'maybe' => t('Maybe'),
      'no' => t('Not'),
    ),
    '#default_value' => $field_data->getSetting('gdpr_fields_rtf', 'no'),
    '#states' => array(
      'visible' => array(
        ':input[name="settings[gdpr_fields_enabled]"]' => array('value' => 1),
      ),
    ),
    '#weight' => 40,
  );

  // If this is the entity's ID, treat the removal as remove the entire
  // entity.
  // @todo Either require gdpr_tasks or move methods out of Anonymizer class.
  if (class_exists('Anonymizer') && Anonymizer::propertyIsEntityId($field_data->entity_type, $field_data->property_name)) {
    unset($form['settings']['gdpr_fields_rtf']['#options']['anonymise']);
    $form['settings']['gdpr_fields_rtf']['#options']['remove'] = t('Delete entire entity');
  }
  // Otherwise check if this can be removed.
  elseif (class_exists('Anonymizer') && !Anonymizer::propertyCanBeRemoved($field_data->entity_type, $field_data->property_name, $info, $error_message)) {
    unset($form['settings']['gdpr_fields_rtf']['#options']['remove']);
    $form['settings']['gdpr_fields_rtf_disabled'] = array(
      '#type' => 'item',
      '#markup' => 'This field cannot be removed, only anonymised.',
      '#description' => $error_message,
      '#weight' => $form['settings']['gdpr_fields_rtf']['#weight'] + 0.1,
    );
  }

  // Force removal to 'no' for computed properties.
  if (!empty($field_data->computed)) {
    $form['settings']['gdpr_fields_rtf']['#default_value'] = 'no';
    $form['settings']['gdpr_fields_rtf']['#disabled'] = TRUE;
    $form['settings']['gdpr_fields_rtf']['#description'] = t('*This is a computed field and cannot be removed.');
  }

  // @todo Filter by relevance.
  $sanitizer_options = array();
  foreach (gdpr_dump_get_sanitizer_plugins() as $plugin_name => $plugin) {
    $sanitizer_options[$plugin_name] = $plugin['title'];
  }

  $form['settings']['gdpr_fields_sanitizer'] = array(
    '#type' => 'select',
    '#title' => t('Sanitizer to use'),
    '#options' => $sanitizer_options,
    '#default_value' => $field_data->getSetting('gdpr_fields_sanitizer', ''),
    '#states' => array(
      'visible' => array(
        ':input[name="settings[gdpr_fields_enabled]"]' => array('value' => 1),
        ':input[name="settings[gdpr_fields_rtf]"]' => array('value' => 'anonymise'),
      ),
    ),
    '#weight' => 50,
  );

  $form['settings']['gdpr_fields_notes'] = array(
    '#type' => 'textarea',
    '#title' => t('Notes'),
    '#default_value' => $field_data->getSetting('gdpr_fields_notes', ''),
    '#states' => array(
      'invisible' => array(
        ':input[name="settings[gdpr_fields_enabled]"]' => array('value' => ''),
      ),
    ),
    '#weight' => 99,
  );
}

/**
 * Define the submit function for the add/edit form.
 */
function gdpr_fields_field_data_export_ui_form_submit(&$form, &$form_state) {
  if ($form_state['values']['settings']['gdpr_fields_enabled'] === '') {
    // Clear all settings.
    $form_state['values']['settings'] = array();
  }
  elseif ($form_state['values']['settings']['gdpr_fields_enabled'] === '0') {
    // Clear field settings, leave notes.
    unset($form_state['values']['settings']['gdpr_fields_rta']);
    unset($form_state['values']['settings']['gdpr_fields_rtf']);
    unset($form_state['values']['settings']['gdpr_fields_sanitizer']);
    unset($form_state['values']['settings']['gdpr_fields_owner']);
    unset($form_state['values']['settings']['gdpr_fields_no_follow']);
    unset($form_state['values']['settings']['gdpr_sars_filename']);
  }

  if (isset($form_state['values']['field']['label'])) {
    $form_state['values']['settings']['label'] = $form_state['values']['field']['label'];
  }
  if (isset($form_state['values']['field']['description'])) {
    $form_state['values']['settings']['description'] = $form_state['values']['field']['description'];
  }
}
