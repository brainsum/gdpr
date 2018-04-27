<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Password\PasswordInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PasswordAnonymizer.
 *
 * @Anonymizer(
 *   id = "password_anonymizer",
 *   label = @Translation("Password anonymizer"),
 *   description = @Translation("Provides anonymization functionality intended to be used for passwords.")
 * )
 *
 * @package Drupal\anonymizer\Plugin\Anonymizer
 */
class PasswordAnonymizer extends AnonymizerBase {

  /**
   * An instance of Random.
   *
   * @var \Drupal\Component\Utility\Random
   */
  protected $random;

  /**
   * The password service.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $password;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('password')
    );
  }

  /**
   * PasswordAnonymizer constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Password\PasswordInterface $password
   *   The password service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PasswordInterface $password
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->password = $password;
    $this->random = new Random();
  }

  /**
   * Return the anonymized input.
   *
   * @var int|string $input
   *   The input.
   *
   * @return int|string
   *   The sanitized input.
   */
  public function anonymize($input, FieldItemListInterface $field = NULL) {
    // @todo: Performance test for lots of data.
    return $this->password->hash($this->random->word(8));
  }

}
