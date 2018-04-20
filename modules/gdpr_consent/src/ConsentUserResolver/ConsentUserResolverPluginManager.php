<?php

namespace Drupal\gdpr_consent\ConsentUserResolver;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Class GdprSanitizerPluginManager.
 *
 * @package Drupal\gdpr_consent\Resolver
 */
class ConsentUserResolverPluginManager extends DefaultPluginManager {


  /**
   * Resolvers keyed by entity type.
   *
   * @var array
   */
  protected $resolvers;

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
      'Plugin/Gdpr/ConsentUserResolver',
      $namespaces,
      $moduleHandler,
      GdprConsentUserResolverInterface::class,
      GdprConsentUserResolver::class
    );

    $this->setCacheBackend($cacheBackend, 'gdpr_consent_resolver_plugins');
    $this->alterInfo('gdpr_consent_resolver_info');
  }


  /**
   * Finds a resolver for the specified entity type/bundle.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return \Drupal\gdpr_consent\ConsentUserResolver\GdprConsentUserResolverInterface
   *   The resolver that will be used to find the User for a specific entity.
   *
   * @throws \Exception
   */
  public function getForEntityType($entity_type, $bundle) {
    $definitions = $this->getDefinitions();

    // Get all plugins that act on the entity type.
    $definitions_for_entity = array_filter($definitions, function ($d) use ($entity_type) {
      return $d['entityType'] == $entity_type;
    });

    $definitions_for_bundle = array_filter($definitions_for_entity, function ($d) use ($bundle) {
      return array_key_exists('bundle', $d) && $d['bundle'] == $bundle;
    });


    if (count($definitions_for_bundle) > 0) {
      // Get first item from the array.
      $definition = reset($definitions_for_bundle);
    }
    elseif (count($definitions_for_entity) > 0) {
      // None matched for bundle.
      // Find any with no bundle.
      $definitions_for_bundle = array_filter($definitions_for_entity, function ($d) {
        return !array_key_exists('bundle', $d) || $d['bundle'] == '';
      });

      if (count($definitions_for_bundle) > 0) {
        // Get first item from array.
        $definition = reset($definitions_for_bundle);
      }
    }

    if (!isset($definition)) {
      throw new \Exception("Could not determine user ID for entity type $entity_type. Please ensure there is a resolver registered.");
    }

    return $this->createInstance($definition['id']);
  }

}
