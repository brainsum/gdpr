<?php

namespace Drupal\gdpr_dump\Sanitizer;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\gdpr_dump\Annotation\GdprSanitizer;

/**
 * Class GdprSanitizerPluginManager.
 *
 * @package Drupal\gdpr_dump\Sanitizer
 *
 * @see \Drupal\gdpr_dump\Sanitizer\GdprSanitizerInterface
 * @see \Drupal\gdpr_dump\Sanitizer\GdprSanitizerBase
 * @see \Drupal\gdpr_dump\Annotation\GdprSanitizer
 * @see plugin_api
 */
class GdprSanitizerPluginManager extends DefaultPluginManager {

  /**
   * Constructs a GdprSanitizerPluginManager object.
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
      'Plugin/Gdpr/Sanitizer',
      $namespaces,
      $moduleHandler,
      GdprSanitizerInterface::class,
      GdprSanitizer::class
    );

    $this->setCacheBackend($cacheBackend, 'gdpr_sanitizer_plugins');
    $this->alterInfo('gdpr_sanitizer_info');
  }

}
