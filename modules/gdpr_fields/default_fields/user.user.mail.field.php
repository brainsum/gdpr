<?php
$field = new GDPRFieldData();
$field->disabled = FALSE; /* Edit this to true to make a default field disabled initially */
$field->name = 'user|user|mail';
$field->plugin_type = 'gdpr_entity_property';
$field->entity_type = 'user';
$field->entity_bundle = 'user';
$field->property_name = 'mail';
$field->settings = array(
  'gdpr_fields_enabled' => '1',
  'gdpr_fields_rta' => 'inc',
  'gdpr_fields_rtf' => 'anonymise',
  'gdpr_fields_sanitizer' => 'gdpr_sanitizer_email',
  'notes' => '',
  'label' => 'Email',
  'description' => NULL,
);
