<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Class ClearAnonymizer.
 *
 * @Anonymizer(
 *   id = "clear_anonymizer",
 *   label = @Translation("Clear anonymizer"),
 *   description = @Translation("Provides anonymizer functionality intended to clear data.")
 * )
 *
 * @package Drupal\anonymizer\Plugin\Anonymizer
 */
class ClearAnonymizer extends AnonymizerBase {

  /**
   * Return an empty output regardless of the input.
   *
   * {@inheritdoc}
   */
  public function anonymize($input, FieldItemListInterface $field = NULL) {
    return '';
  }

}
