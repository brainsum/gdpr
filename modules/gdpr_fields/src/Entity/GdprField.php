<?php

namespace Drupal\gdpr_fields\Entity;

/**
 * Metadata for a GDPR field.
 */
class GdprField {

  /**
   * Bundle name.
   *
   * @var string
   */
  public $bundle;

  /**
   * Field name.
   *
   * @var string
   */
  public $name;

  /**
   * Whether GDPR is enabled for this field.
   *
   * @var bool
   */
  public $enabled = FALSE;

  /**
   * Right to Forget setting for this field.
   *
   * @var string
   */
  public $rtf = 'no';

  /**
   * Right to Access setting for this field.
   *
   * @var string
   */
  public $rta = 'no';

  /**
   * Anonymizer to use on this field.
   *
   * @var string
   */
  public $anonymizer = '';

  /**
   * Notes.
   *
   * @var string
   */
  public $notes = '';

  /**
   * Whether this field has been configured for GDPR.
   *
   * This is different for enabled -
   * something can be configured but not enabled.
   *
   * @var bool
   */
  public $configured = FALSE;

  /**
   * GdprField constructor.
   *
   * @param string $bundle
   *   Bundle.
   * @param string $name
   *   Field name.
   */
  public function __construct($bundle, $name) {
    $this->bundle = $bundle;
    $this->name = $name;
  }

  /**
   * Creates a GdprField instance based on array data from the config entity.
   *
   * @param array $values
   *   The underlying data.
   *
   * @return \Drupal\gdpr_fields\Entity\GdprField
   *   The field metadata instance.
   */
  public static function create(array $values) {
    $field = new static($values['bundle'], $values['name']);
    $field->rtf = $values['rtf'];
    $field->rta = $values['rta'];
    $field->enabled = $values['enabled'];
    $field->anonymizer = $values['anonymizer'];
    $field->notes = $values['notes'];
    $field->configured = TRUE;
    return $field;
  }

  /**
   * Gets the RTF description.
   *
   * @return string
   *   The description.
   */
  public function rtfDescription() {
    switch ($this->rtf) {
      case 'anonymize':
        return 'Anonymize';

      case 'remove':
        return 'Remove';

      case 'maybe':
        return 'Maybe';

      case 'no':
        return 'Not Included';

      default:
        return 'Not Configured';

    }
  }

  /**
   * Gets the RTA description.
   *
   * @return string
   *   The description.
   */
  public function rtaDescription() {
    switch ($this->rta) {
      case 'inc':
        return 'Included';

      case 'maybe':
        return 'Maybe';

      case 'no':
        return 'Not Included';

      default:
        return 'Not Configured';

    }
  }

}
