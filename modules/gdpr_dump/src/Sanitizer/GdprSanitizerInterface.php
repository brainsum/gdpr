<?php

namespace Drupal\gdpr_dump\Sanitizer;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Interface GdprSanitizerInterface.
 *
 * @package Drupal\gdpr_dump\Sanitizer
 */
interface GdprSanitizerInterface {

  /**
   * Return the sanitized input.
   *
   * @var int|string $input
   *   The input.
   * @var FieldItemListInterface|null $field
   *   The field being sanitized.
   *
   * @return int|string
   *   The sanitized input.
   */
  public function sanitize($input, FieldItemListInterface $field = NULL);

}
