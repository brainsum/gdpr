<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Component\Utility\Random;
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
   * Constant for email length.
   */
  const EMAIL_LENGTH = 12;

  /**
   * An instance of Random.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected $random;

  /**
   * {@inheritdoc}
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

    return $this->random->word(self::EMAIL_LENGTH) . '@example.com';
  }

}
