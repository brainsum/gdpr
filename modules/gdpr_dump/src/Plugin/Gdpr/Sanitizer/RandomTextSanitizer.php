<?php

namespace Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\gdpr_dump\Sanitizer\GdprSanitizerBase;

/**
 * Class TextSanitizer.
 *
 * @GdprSanitizer(
 *   id = "gdpr_random_text_sanitizer",
 *   label = @Translation("Random Text sanitizer"),
 *   description=@Translation("Provides sanitation functionality intended to be
 *   used for text fields.")
 * )
 *
 * @package Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer
 */
class RandomTextSanitizer extends GdprSanitizerBase {

  /**
   * {@inheritdoc}
   */
  public function sanitize($input, FieldItemListInterface $field = NULL) {
    if (!is_null($field)) {
      $max_length = $field->getDataDefinition()->getSetting("max_length");
    }

    $value = '';

    if (!empty($input)) {
      // Generate a prefixed random string.
      $rand = new Random();
      $value = "anon_" . $rand->string(4);
      // If the value is too long, tirm it.
      if (isset($max_length) && strlen($input) > $max_length) {
        $value = substr(0, $max_length);
      }
    }
    return $value;
  }

}
