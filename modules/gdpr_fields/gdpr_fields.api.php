<?php

/**
 * @file
 * Hooks provided by the GDPR fields module.
 */

/**
 * Imports ctools exportable GDPR field settings from code.
 *
 * @return array
 *   An array of field settings definitions.
 */
function hook_gdpr_fields_default_field_data() {
  $export = array();

  $field = new GDPRFieldData();
  $field->disabled = FALSE; /* Edit this to true to make a default field disabled initially */
  $field->name = 'user|user|mail';
  $field->entity_type = 'user';
  $field->entity_bundle = 'user';
  $field->property_name = 'mail';
  $field->settings = array(
    'gdpr_fields_enabled' => '1',
    'gdpr_fields_rta' => 'inc',
    'gdpr_fields_rtf' => 'anonymise',
    'gdpr_fields_sanitizer' => 'EmailSanitizer',
    'gdpr_fields_notes' => '',
    'label' => 'Email',
  );
  $export['user|user|mail'] = $field;

  return $export;
}
