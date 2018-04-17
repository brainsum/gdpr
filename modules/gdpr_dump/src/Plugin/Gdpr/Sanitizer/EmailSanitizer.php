<?php

namespace Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\gdpr_dump\Sanitizer\GdprSanitizerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EmailSanitizer.
 *
 * @GdprSanitizer(
 *   id = "gdpr_email_sanitizer",
 *   label = @Translation("Email sanitizer"),
 *   description=@Translation("Provides sanitation functionality intended to be used for emails.")
 * )
 *
 * @package Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer
 */
class EmailSanitizer extends GdprSanitizerBase {

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
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function sanitize($input, FieldItemListInterface $field = NULL) {
    if (empty($input)) {
      return $input;
    }

    if (NULL === $this->random) {
      $this->random = new Random();
    }

    return $this->random->word(self::EMAIL_LENGTH) . '@example.com';
  }

}
