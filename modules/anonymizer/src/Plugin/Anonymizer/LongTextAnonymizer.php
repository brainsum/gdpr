<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Class LongTextAnonymizer.
 *
 * @Anonymizer(
 *   id = "long_text_anonymizer",
 *   label = @Translation("Long text anonymizer"),
 *   description = @Translation("Provides anonymization functionality intended to be used for longer text.")
 * )
 *
 * @package Drupal\anonymizer\Plugin\Anonymizer
 */
class LongTextAnonymizer extends AnonymizerBase {

  const MAX_SENTENCE_COUNT = 24;

  /**
   * {@inheritdoc}
   */
  public function anonymize($input, FieldItemListInterface $field = NULL) {
    return $this->faker->generator()->paragraph(self::MAX_SENTENCE_COUNT);
  }

}
