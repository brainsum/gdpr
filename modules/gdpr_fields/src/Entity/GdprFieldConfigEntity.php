<?php

namespace Drupal\gdpr_fields\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines a Flower configuration entity class.
 *
 * @ConfigEntityType(
 *   id = "gdpr_fields_config",
 *   label = @Translation("GDPR Fields"),
 *   handlers = {
 *   },
 *   config_prefix = "gdpr_fields_config",
 *   admin_permission = "view gdpr fields",
 *   entity_keys = {
 *     "id" = "id"
 *   },
 *   links = {
 *     "add-form" = "/admin/gdpr/fields/add",
 *     "edit-form" = "/admin/gdpr/fields/{gdpr_fields_config}/edit2",
 *     "delete-form" = "/admin/gdpr/fields/{gdpr_fields_config}/delete"
 *   }
 * )
 */
class GdprFieldConfigEntity extends ConfigEntityBase {

  /**
   * Associative array.
   *
   * Each element is keyed by bundle name and contains an array representing
   * a list of fields.
   *
   * Each field is in turn represented as a nested array.
   *
   * @var array
   */
  public $bundles = [];

  /**
   * Sets a GDPR field's settings.
   *
   * @param string $bundle
   *   Bundle.
   * @param string $field_name
   *   Field.
   * @param array $values
   *   Additional values. Keys should be enabled, rtf, rta, anonymizer, notes.
   *
   * @return $this
   */
  public function setField($bundle, $field_name, array $values) {
    $values['bundle'] = $bundle;
    $values['name'] = $field_name;

    foreach ($values as $key => $value) {
      $this->bundles[$bundle][$field_name][$key] = $value;
    }

    return $this;
  }

  /**
   * Gets field metadata.
   *
   * @param string $bundle
   *   Bundle.
   * @param string $field_name
   *   Field name.
   *
   * @return \Drupal\gdpr_fields\Entity\GdprField
   *   Field metadata.
   */
  public function getField($bundle, $field_name) {
    $bundle_fields = \array_filter($this->bundles, function ($key) use ($bundle) {
      return $key == $bundle;
    }, ARRAY_FILTER_USE_KEY);

    if (\count($bundle_fields)) {
      $result = \array_filter($bundle_fields[$bundle], function ($f) use ($field_name) {
        return $f['name'] == $field_name;
      });

      if (\count($result)) {
        return GdprField::create(\reset($result));
      }
    }

    return new GdprField($bundle, $field_name);
  }

}
