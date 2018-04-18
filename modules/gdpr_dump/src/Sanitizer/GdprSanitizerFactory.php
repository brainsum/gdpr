<?php

namespace Drupal\gdpr_dump\Sanitizer;

/**
 * Class GdprSanitizerFactory.
 *
 * @package Drupal\gdpr_dump\Sanitizer
 */
class GdprSanitizerFactory {

  /**
   * Sanitizer instances keyed by ID.
   *
   * @var array
   */
  protected $sanitizers = [];

  /**
   * The plugin manager.
   *
   * @var \Drupal\gdpr_dump\Sanitizer\GdprSanitizerPluginManager
   */
  protected $pluginManager;

  /**
   * GdprSanitizerFactory constructor.
   *
   * @param \Drupal\gdpr_dump\Sanitizer\GdprSanitizerPluginManager $pluginManager
   *   The GDPR plugin manager.
   */
  public function __construct(
    GdprSanitizerPluginManager $pluginManager
  ) {
    $this->pluginManager = $pluginManager;
  }

  /**
   * Get an instance of a sanitizer.
   *
   * @param string $name
   *   Sanitizer name.
   *
   * @return \Drupal\gdpr_dump\Sanitizer\GdprSanitizerInterface
   *   The sanitizer instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function get($name) {
    if (!isset($this->sanitizers[$name])) {
      $this->sanitizers[$name] = $this->pluginManager->createInstance($name);
    }

    return $this->sanitizers[$name];
  }


  /**
   * Gets all sanitizers currently registered.
   */
  public function getDefinitions() {
    return $this->pluginManager->getDefinitions();
  }

}
