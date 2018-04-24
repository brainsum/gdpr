<?php

namespace Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\gdpr_dump\Sanitizer\GdprSanitizerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UriSanitizer.
 *
 * @GdprSanitizer(
 *   id = "gdpr_uri_sanitizer",
 *   label = @Translation("Uri sanitizer"),
 *   description = @Translation("Provides sanitation functionality for uri's")
 * )
 *
 * @package Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer
 */
class UriSanitizer extends GdprSanitizerBase {

  /**
   * The drupal random.
   *
   * @var \Drupal\Component\Utility\Random
   */
  private $randomizer;

  /**
   * UriSanitizer constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Utility\Random $randomizer
   *   The Random library.
   */
  public function __construct(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    Random $randomizer
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $this->setContainer($container);
    $this->randomizer = $randomizer;
  }

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
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition,
      new Random()
    );
  }

  /**
   * Sanitize an url into some random url.
   *
   * @var int|string $input
   *   The input.
   * @var \Drupal\Core\Field\FieldItemListInterface|null $field
   *   The field being sanitized.
   *
   * @return int|string
   *   The sanitized input.
   */
  public function sanitize($input, FieldItemListInterface $field = NULL) {
    if (empty($input)) {
      return $input;
    }

    return sprintf(
      'https://%s.tld/%s',
      $this->randomizer->word(12),
      $this->randomizer->word(12)
    );
  }

}
