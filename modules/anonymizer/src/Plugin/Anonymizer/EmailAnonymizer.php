<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Class EmailAnonymizer.
 *
 * @Anonymizer(
 *   id = "email_anonymizer",
 *   label = @Translation("Email anonymizer"),
 *   description = @Translation("Provides anonymization functionality intended to be used for emails.")
 * )
 *
 * @package Drupal\anonymizer\Plugin\Anonymizer
 */
class EmailAnonymizer extends AnonymizerBase {

  /**
   * {@inheritdoc}
   */
  public function anonymize($input, FieldItemListInterface $field = NULL) {
    return $this->faker->generator()->unique()->safeEmail;
  }

}
