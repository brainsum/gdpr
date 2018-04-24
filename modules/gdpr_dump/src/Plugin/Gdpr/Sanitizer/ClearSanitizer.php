<?php

namespace Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\gdpr_dump\Sanitizer\GdprSanitizerBase;

/**
 * Class ClearSanitizer.
 *
 * @GdprSanitizer(
 *   id = "gdpr_clear_sanitizer",
 *   label = @Translation("Clear sanitizer"),
 *   description=@Translation("Provides sanitation functionality intended to clear data.")
 * )
 *
 * @package Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer
 */
class ClearSanitizer extends GdprSanitizerBase {

  /**
   * Sanitize by clearing the content whole.
   *
   * @var int|string $input
   *   The input.
   * @var \Drupal\Core\Field\FieldItemListInterface|null $field
   *   The field being sanitized.
   *
   * @return int|string
   *   The sanitized output.
   */
  public function sanitize($input, FieldItemListInterface $field = NULL) {
    return '';
  }

}
