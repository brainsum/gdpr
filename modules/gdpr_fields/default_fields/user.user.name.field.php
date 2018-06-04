<?php
$field = new GDPRFieldData();
$field->disabled = FALSE; /* Edit this to true to make a default field disabled initially */
$field->name = 'user|user|name';
$field->plugin_type = 'gdpr_entity_property';
$field->entity_type = 'user';
$field->entity_bundle = 'user';
$field->property_name = 'name';
$field->settings = array(
  'gdpr_fields_enabled' => '1',
  'gdpr_fields_rta' => 'maybe',
  'gdpr_fields_rtf' => 'anonymise',
  'gdpr_fields_sanitizer' => 'gdpr_sanitizer_username',
  'notes' => '',
  'label' => 'Name',
  'description' => NULL,
);
