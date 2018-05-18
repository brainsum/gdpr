<?php

namespace Drupal\anonymizer\Anonymizer;

use Drupal\anonymizer\Service\FakerServiceInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AnonymizerBase.
 *
 * @package Drupal\anonymizer\Anonymizer
 */
abstract class AnonymizerBase extends PluginBase implements AnonymizerInterface, ContainerFactoryPluginInterface {

  use ContainerAwareTrait;

  /**
   * The faker service.
   *
   * @var \Drupal\anonymizer\Service\FakerServiceInterface
   */
  protected $faker;

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
   * @param \Drupal\anonymizer\Service\FakerServiceInterface $faker
   *   The faker service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    FakerServiceInterface $faker
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->faker = $faker;
  }

}
