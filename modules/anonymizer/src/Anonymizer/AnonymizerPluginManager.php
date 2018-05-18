<?php

namespace Drupal\anonymizer\Anonymizer;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\anonymizer\Annotation\Anonymizer;

/**
 * Manager class for Anonymizer plugins.
 *
 * @package Drupal\anonymizer\Anonymizer
 *
 * @see \Drupal\anonymizer\Anonymizer\AnonymizerInterface
 * @see \Drupal\anonymizer\Anonymizer\AnonymizerBase
 * @see \Drupal\anonymizer\Annotation\Anonymizer
 * @see plugin_api
 */
class AnonymizerPluginManager extends DefaultPluginManager {

  /**
   * Constructs an AnonymizerPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler
  ) {
    parent::__construct(
      'Plugin/Anonymizer',
      $namespaces,
      $moduleHandler,
      AnonymizerInterface::class,
      Anonymizer::class
    );

    $this->setCacheBackend($cacheBackend, 'anonymizer_plugins');
    $this->alterInfo('anonymizer_info');
  }

}
