<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Component\Utility\Random;
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

    return sprintf(
      'https://%s.tld/%s',
      $this->random->word(12),
      $this->random->word(12)
    );
  }

}
