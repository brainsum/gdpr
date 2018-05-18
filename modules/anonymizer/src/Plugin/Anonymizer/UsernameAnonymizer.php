<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Class UsernameAnonymizer.
 *
 * @Anonymizer(
 *   id = "username_anonymizer",
 *   label = @Translation("Username anonymizer"),
 *   description = @Translation("Provides anonymization functionality intended to be used for user names.")
 * )
 *
 * @package Drupal\anonymizer\Plugin\Anonymizer
 */
class UsernameAnonymizer extends AnonymizerBase {

  /**
   * {@inheritdoc}
   *
   * @throws \RuntimeException
   */
  public function anonymize($input, FieldItemListInterface $field = NULL) {
    return $this->faker->generator()->unique()->userName;
  }

}
