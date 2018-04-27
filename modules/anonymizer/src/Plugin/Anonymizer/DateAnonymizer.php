<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Class TextAnonymizer.
 *
 * @Anonymizer(
 *   id = "date_anonymizer",
 *   label = @Translation("Date anonymizer"),
 *   description = @Translation("Provides anonymization functionality intended to be used for datetime fields.")
 * )
 *
 * @package Drupal\anonymizer\Plugin\Anonymizer
 */
class DateAnonymizer extends AnonymizerBase {

  /**
   * {@inheritdoc}
   */
  public function anonymize($input, FieldItemListInterface $field = NULL) {
    return $this->faker->generator()->date();
  }

}
