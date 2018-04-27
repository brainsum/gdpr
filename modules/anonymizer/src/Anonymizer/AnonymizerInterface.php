<?php

namespace Drupal\anonymizer\Anonymizer;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Interface AnonymizerInterface.
 *
 * @package Drupal\anonymizer\Anonymizer
 */
interface AnonymizerInterface {

  /**
   * Return an anonymized output.
   *
   * @var int|string $input
   *   The input.
   * @var \Drupal\Core\Field\FieldItemListInterface|null $field
   *   The field being anonymized.
   *
   * @return int|string
   *   The anonymized output.
   */
  public function anonymize($input, FieldItemListInterface $field = NULL);

}
