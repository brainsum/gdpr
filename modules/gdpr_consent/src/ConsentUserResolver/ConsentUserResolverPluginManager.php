<?php

namespace Drupal\gdpr_consent\ConsentUserResolver;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Class ConsentUserResolverPluginManager.
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
   * Constructs a ConsentUserResolverPluginManager object.
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
   * Check for an existing resolver for the specified entity type/bundle.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return array|bool
   *   The resolver definition, if it exists, or NULL.
   *
   * @throws \Exception
   */
  public function getDefinitionForType($entityType, $bundle) {
    $definitions = $this->getDefinitions();

    // Get all plugins that act on the entity type.
    $definitionsForEntity = \array_filter($definitions, function ($definition) use ($entityType) {
      return $definition['entityType'] === $entityType;
    });

    $definitionsForBundle = \array_filter($definitionsForEntity, function ($definition) use ($bundle) {
      return array_key_exists('bundle', $definition) && $definition['bundle'] === $bundle;
    });

    $definition = NULL;
    if (\count($definitionsForBundle) > 0) {
      // Get first item from the array.
      $definition = \reset($definitionsForBundle);
    }
    elseif (\count($definitionsForEntity) > 0) {
      // None matched for bundle.
      // Find any with no bundle.
      $definitionsForBundle = \array_filter($definitionsForEntity, function ($definition) {
        return !array_key_exists('bundle', $definition) || $definition['bundle'] === '';
      });

      if (\count($definitionsForBundle) > 0) {
        // Get first item from array.
        $definition = \reset($definitionsForBundle);
      }
    }

    if (NULL === $definition) {
      return FALSE;
    }

    return $definition;
  }

  /**
   * Finds a resolver for the specified entity type/bundle.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return \Drupal\gdpr_consent\ConsentUserResolver\GdprConsentUserResolverInterface
   *   The resolver that will be used to find the User for a specific entity.
   *
   * @throws \Exception
   */
  public function getForEntityType($entityType, $bundle) {
    $definition = $this->getDefinitionForType($entityType, $bundle);

    if (FALSE === $definition) {
      throw new \Exception("Could not determine user ID for entity type $entityType. Please ensure there is a resolver registered.");
    }

    return $this->createInstance($definition['id']);
  }

}
