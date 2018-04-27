<?php

namespace Drupal\anonymizer\Anonymizer;

/**
 * Class AnonymizerFactory.
 *
 * @package Drupal\anonymizer\Anonymizer
 */
class AnonymizerFactory {

  /**
   * Anonymizer instances keyed by their ID.
   *
   * @var \Drupal\anonymizer\Anonymizer\AnonymizerInterface[]
   */
  protected $anonymizers = [];

  /**
   * The anonymizer plugin manager.
   *
   * @var \Drupal\anonymizer\Anonymizer\AnonymizerPluginManager
   */
  protected $pluginManager;

  /**
   * AnonymizerFactory constructor.
   *
   * @param \Drupal\anonymizer\Anonymizer\AnonymizerPluginManager $pluginManager
   *   The anonymizer plugin manager.
   */
  public function __construct(AnonymizerPluginManager $pluginManager) {
    $this->pluginManager = $pluginManager;
  }

  /**
   * Gets all anonymizers currently registered.
   */
  public function getDefinitions() {
    return $this->pluginManager->getDefinitions();
  }

  /**
   * Get an instance of a anonymizer.
   *
   * @param string $name
   *   Anonymizer name.
   *
   * @return \Drupal\anonymizer\Anonymizer\AnonymizerInterface
   *   The anonymizer instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function get($name) {
    if (!isset($this->anonymizers[$name])) {
      $this->anonymizers[$name] = $this->pluginManager->createInstance($name);
    }

    return $this->anonymizers[$name];
  }

}
