<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Class TextAnonymizer.
 *
 * @Anonymizer(
 *   id = "text_anonymizer",
 *   label = @Translation("Text anonymizer"),
 *   description = @Translation("Provides anonymization functionality intended to be used for titles or short text.")
 * )
 *
 * @package Drupal\anonymizer\Plugin\Anonymizer
 */
class TextAnonymizer extends AnonymizerBase {

  /**
   * {@inheritdoc}
   *
   * @throws \RuntimeException
   */
  public function anonymize($input, FieldItemListInterface $field = NULL) {
    return $this->faker->generator()->words(\str_word_count($input), TRUE);
  }

}
