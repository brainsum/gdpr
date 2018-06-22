<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Class RandomTextAnonymizer.
 *
 * @Anonymizer(
 *   id = "random_text_anonymizer",
 *   label = @Translation("Random Text anonymizer"),
 *   description = @Translation("Provides anonymization functionality intended to be used for text fields.")
 * )
 *
 * @package Drupal\anonymizer\Plugin\Anonymizer
 */
class RandomTextAnonymizer extends AnonymizerBase {

  /**
   * {@inheritdoc}
   */
  public function anonymize($input, FieldItemListInterface $field = NULL) {
    if (empty($input)) {
      return $input;
    }

    $maxLength = NULL;
    if (NULL !== $field) {
      $maxLength = $field->getDataDefinition()->getSetting('max_length');
    }

    // Generate a prefixed random string.
    $value = 'anon_' . $this->faker->generator()->words(1, TRUE);
    // If the value is too long, trim it.
    if ($maxLength !== NULL && (\strlen($input) > $maxLength)) {
      $value = \substr(0, $maxLength);
    }

    return $value;
  }

}
