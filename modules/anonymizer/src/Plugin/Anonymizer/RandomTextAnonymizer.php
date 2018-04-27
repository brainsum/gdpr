<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Class RandomTextAnonymizer.
 *
 * @Anonymizer(
 *   id = "random_text_anonymizer",
 *   label = @Translation("Random Text anonymizer"),
 *   description = @Translation("Provides anonymization functionality intended to be
 *   used for text fields.")
 * )
 *
 * @package Drupal\anonymizer\Plugin\Anonymizer
 */
class RandomTextAnonymizer extends AnonymizerBase {

  /**
   * An instance of Random.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected $random;

  /**
   * PasswordAnonymizer constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->random = new Random();
  }

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
    $value = 'anon_' . $this->random->string(4);
    // If the value is too long, trim it.
    if ($maxLength !== NULL && (\strlen($input) > $maxLength)) {
      $value = \substr(0, $maxLength);
    }

    return $value;
  }

}
