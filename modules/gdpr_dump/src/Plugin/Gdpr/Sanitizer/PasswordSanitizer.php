<?php

namespace Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\gdpr_dump\Sanitizer\GdprSanitizerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PasswordSanitizer.
 *
 * @GdprSanitizer(
 *   id = "gdpr_password_sanitizer",
 *   label = @Translation("Password sanitizer"),
 *   description=@Translation("Provides sanitation functionality intended to be used for passwords.")
 * )
 *
 * @package Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer
 */
class PasswordSanitizer extends GdprSanitizerBase {

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
   * Return the sanitized input.
   *
   * @var int|string $input
   *   The input.
   *
   * @return int|string
   *   The sanitized input.
   */
  public function sanitize($input, FieldItemListInterface $field = NULL) {
    if (NULL === $this->random) {
      $this->random = new Random();
    }

    if (NULL === $this->password) {
      // @todo: Dep.inj.
      $this->password = \Drupal::service('password');
    }

    // @todo: Performance test for lots of data.
    return $this->password->hash($this->random->word(8));
  }

}
