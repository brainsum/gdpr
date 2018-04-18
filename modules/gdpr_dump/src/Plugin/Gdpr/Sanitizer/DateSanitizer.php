<?php

namespace Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\gdpr_dump\Sanitizer\GdprSanitizerBase;

/**
 * Class TextSanitizer.
 *
 * @GdprSanitizer(
 *   id = "gdpr_date_sanitizer",
 *   label = @Translation("Date sanitizer"),
 *   description=@Translation("Provides sanitation functionality intended to be used for datetime fields.")
 * )
 *
 * @package Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer
 */
class DateSanitizer extends GdprSanitizerBase {

  /**
   * {@inheritdoc}
   */
  public function sanitize($input, FieldItemListInterface $field = NULL) {
    return '1000-01-01';
  }

}
