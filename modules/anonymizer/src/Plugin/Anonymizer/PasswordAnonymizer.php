<?php

namespace Drupal\anonymizer\Plugin\Anonymizer;

use Drupal\anonymizer\Anonymizer\AnonymizerBase;
use Drupal\anonymizer\Service\FakerServiceInterface;
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

  const MIN_PASSWORD_LENGTH = 10;
  const MAX_PASSWORD_LENGTH = 20;

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
      $container->get('password'),
      $container->get('anonymizer.faker')
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
   * @param \Drupal\anonymizer\Service\FakerServiceInterface $faker
   *   The faker service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    PasswordInterface $password,
    FakerServiceInterface $faker
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $faker);
    $this->password = $password;
  }

  /**
   * {@inheritdoc}
   */
  public function anonymize($input, FieldItemListInterface $field = NULL) {
    // @todo: Performance test for lots of data.
    return $this->password->hash($this->faker->generator()->password(self::MIN_PASSWORD_LENGTH, self::MAX_PASSWORD_LENGTH));
  }

}
