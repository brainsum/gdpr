<?php

/**
 * Anonymizes or removes field values for GDPR.
 */
class Anonymizer {

  /**
   * Errors encountered by anonymization.
   *
   * @var array
   */
  public $errors = array();

  /**
   * Entities successfully anonymized.
   *
   * Array is keyed by entity_type and entity_id.
   *
   * @var array
   */
  public $successes = array();

  /**
   * Entities that failed to anonymize.
   *
   * @var array
   */
  public $failures = array();

  /**
   * Runs anonymization routines against a user.
   *
   * @param GDPRTask $task
   *   The current task being executed.
   *
   * @return array
   *   Returns array containing any error messages.
   */
  public function run(GDPRTask $task) {
    // Make sure we load a fresh copy of the entity (bypassing the cache)
    // so we don't end up affecting any other references to the entity.
    $user = $task->getOwner();

    $log = array();

    if (!$this->checkExportDirectoryExists()) {
      $this->errors[] = 'An export directory has not been set. Please set this under Configuration -> GDPR -> Right to be Forgotten';
    }

    foreach (gdpr_tasks_collect_rtf_data($user, TRUE) as $data) {
      $mode = $data['rtf'];
      $entity_type = $data['entity_type'];
      $entity_id = $data['entity_id'];
      $entity = $data['entity'];
      $wrapper = entity_metadata_wrapper($entity_type, $entity_id);
      $entity_bundle = $wrapper->type();

      $entity_success = TRUE;
      $success = TRUE;
      $msg = NULL;
      $sanitizer = '';

      if ($mode == 'anonymise') {
        list($success, $msg, $sanitizer) = $this->anonymize($data, $entity);
      }
      elseif ($mode == 'remove') {
        list($success, $msg) = $this->remove($data, $entity);
      }

      if ($success === TRUE) {
        $log[] = 'success';
        $log[] = array(
          'entity_id' => $entity_id,
          'entity_type' => $entity_type . '.' . $entity_bundle,
          'field_name' => $data['plugin']->property_name,
          'action' => $mode,
          'sanitizer' => $sanitizer,
        );
      }
      else {
        // Could not anonymize/remove field. Record to errors list.
        // Prevent entity from being saved.
        $entity_success = FALSE;
        $this->errors[] = $msg;
        $log[] = 'error';
        $log[] = array(
          'error' => $msg,
          'entity_id' => $entity_id,
          'entity_type' => $entity_type . '.' . $entity_bundle,
          'field_name' => $data['plugin']->property_name,
          'action' => $mode,
          'sanitizer' => $sanitizer,
        );
      }

      if ($entity_success) {
        $this->successes[$entity_type][$entity_id] = $entity;
      }
      else {
        $this->failures[] = $entity;
      }
    }

    // @todo Better log field.
    $task->wrapper()->gdpr_tasks_removal_log = json_encode($log);

    $this->complete($task);

    return $this->errors;
  }

  /**
   * Complete anonymization routines.
   *
   * @param GDPRTask $task
   *   The current task being executed.
   */
  protected function complete(GDPRTask $task) {
    if (count($this->failures) === 0) {
      $tx = db_transaction();

      try {
        /* @var EntityInterface $entity */
        foreach ($this->successes as $entity_type => $entities) {
          foreach ($entities as $entity) {
            entity_save($entity_type, $entity);
          }
        }
        // Re-fetch the user so we see any changes that were made.
        $user = entity_load_unchanged('user', $task->user_id);
        user_save($user, array('status' => 0));

        // @todo Write a log to file system.
      }
      catch (\Exception $e) {
        $tx->rollback();
        $this->errors[] = $e->getMessage();
      }
    }
  }

  /**
   * Removes the field value.
   *
   * @param array $field_info
   *   The current field to process.
   * @param object|EntityInterface $entity
   *   The current field to process.
   *
   * @return array
   *   First element is success boolean, second element is the error message.
   */
  private function remove(array $field_info, $entity) {
    try {
      $entity_type = $field_info['entity_type'];
      $field = $field_info['plugin']->property_name;

      // If this is the entity's ID, treat the removal as remove the entire
      // entity.
      if (self::propertyIsEntityId($entity_type, $field)) {
        if (entity_delete($entity_type, $entity->{$field}) === FALSE) {
          return array(FALSE, "Unable to delete entity type.");
        }
        return array(TRUE, NULL);
      }

      // Check if the property can be removed.
      $wrapper = entity_metadata_wrapper($entity_type, $entity);
      if (!self::propertyCanBeRemoved($entity_type, $field, $wrapper->{$field}->info(), $error_message)) {
        return array(FALSE, $error_message);
      }

      // Otherwise assume we can simply clear the field.
      $entity->{$field} = NULL;
      return array(TRUE, NULL);
    }
    catch (Exception $e) {
      return array(FALSE, $e->getMessage());
    }
  }

  /**
   * Runs anonymize functionality against a field.
   *
   * @param array $field_info
   *   The field to anonymise.
   * @param object|EntityInterface $entity
   *   The parent entity.
   *
   * @return array
   *   First element is success boolean, second element is the error message.
   */
  private function anonymize(array $field_info, $entity) {
    $sanitizer_id = $this->getSanitizerId($field_info, $entity);
    $field = $field_info['plugin']->property_name;

    if (!$sanitizer_id) {
      return array(
        FALSE,
        "Could not anonymize field {$field}. Please consider changing this field from 'anonymize' to 'remove', or register a custom sanitizer.",
        NULL,
      );
    }

    try {
      $plugin = gdpr_dump_get_sanitizer_plugins($sanitizer_id);
      $wrapper = entity_metadata_wrapper($field_info['entity_type'], $entity);
      $entity_property_info = $wrapper->getPropertyInfo();
      if (function_exists($plugin['sanitize callback'])) {
        $type = isset($entity_property_info[$field]['type']) ? $entity_property_info[$field]['type'] : 'string';

        if ($type == 'text_formatted') {
          $wrapper->{$field} = [
            'value' => call_user_func($plugin['sanitize callback'], $field_info['value']),
            'safe_value' => '',
          ];
        } else {
          $wrapper->{$field} = call_user_func($plugin['sanitize callback'], $field_info['value']);
        }
        return array(TRUE, NULL, $sanitizer_id);
      }
      else {
        throw new \Exception("No sanitizer found for field {$field}.");
      }
    }
    catch (\Exception $e) {
      return array(FALSE, $e->getMessage(), NULL);
    }
  }

  /**
   * Checks that the export directory has been set.
   *
   * @return bool
   *   Indicates whether the export directory has been configured and exists.
   */
  private function checkExportDirectoryExists() {
    // @todo Configure export directory.
    $directory = 'private://gdpr-export';

    return !empty($directory) && file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
  }

  /**
   * Gets the ID of the sanitizer plugin to use on this field.
   *
   * @param array $field_info
   *   The field to anonymise.
   *
   * @return string
   *   The sanitizer ID or null.
   */
  private function getSanitizerId(array $field_info) {
    // First check if this field has a sanitizer defined.
    $sanitizer = $field_info['plugin']->settings['gdpr_fields_sanitizer'];

    // @todo Allow sanitizers to fall back to type selection relevant for the field type.
    if (!$sanitizer) {
      $sanitizer = 'gdpr_sanitizer_text';
    }
    return $sanitizer;
  }

  /**
   * Check whether a property is the entity ID.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $field
   *   The field name.
   *
   * @return bool
   *   Whether the property is the entity ID.
   */
  public static function propertyIsEntityId($entity_type, $field) {
    $entity_info = entity_get_info($entity_type);
    return $entity_info['entity keys']['id'] == $field;
  }

  /**
   * Check whether a property can be removed.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $field
   *   The field name.
   * @param array $property_info
   *   The property info.
   * @param mixed $error_message
   *   A variable to fill with an error message.
   *
   * @return bool
   *   TRUE if the property can be removed, FALSE if not.
   */
  public static function propertyCanBeRemoved($entity_type, $field, array $property_info, &$error_message = NULL) {
    // Fail on computed fields.
    if (!empty($property_info['computed'])) {
      $error_message = "Unable to remove computed property.";
      return FALSE;
    }

    // Check that this isn't a required entity property.
    $entity_info = entity_get_info($entity_type);
    if (!empty($entity_info['base table']) && empty($property_info['field'])) {
      $schema_field = isset($property_info['schema field']) ? $property_info['schema field'] : $field;
      $schema = drupal_get_schema($entity_info['base table']);

      // If the field is set to not NULL, fail.
      if (!empty($schema['fields'][$schema_field]['not null'])) {
        $error_message = t("Unable to remove required database field %field.", array('%field' => $field));
        return FALSE;
      }

      if (in_array($schema_field, $schema['primary key'])) {
        $error_message = t("Unable to remove part of a primary key %field.", array('%field' => $field));
        return FALSE;
      }
    }

    // Otherwise assume we can.
    return TRUE;
  }

}
