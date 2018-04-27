<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Class UriAnonymizer.
 *
 * @Anonymizer(
 *   id = "uri_anonymizer",
 *   label = @Translation("Uri anonymizer"),
 *   description = @Translation("Provides anonymization functionality for uri's")
 * )
 *
 * @package Drupal\anonymizer\Plugin\Anonymizer
 */
class UriAnonymizer extends AnonymizerBase {

  /**
   * {@inheritdoc}
   */
  public function anonymize($input, FieldItemListInterface $field = NULL) {
    // @todo: Force https?
    return $this->faker->generator()->url;
  }

}
