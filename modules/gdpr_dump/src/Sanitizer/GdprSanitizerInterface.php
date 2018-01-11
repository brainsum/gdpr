<?php

namespace Drupal\gdpr_dump\Sanitizer;

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
   *
   * @return int|string
   *   The sanitized input.
   */
  public function sanitize($input);

}
