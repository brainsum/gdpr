<?php

namespace Drupal\gdpr_consent\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\gdpr_consent\Controller\ConsentAgreementController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to view a contact dashboard summary.
 *
 * @Block(
 *   id = "gdpr_agreements_block",
 *   admin_label = @Translation("GDPR Agreements Accepted"),
 *   category = @Translation("Dashboard Blocks"),
 *   dashboard_block = TRUE
 * )
 */
class GdprMyAgreementsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * The user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition
  ) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('class_resolver'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $classResolver
   *   The class resolver service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The user.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    ClassResolverInterface $classResolver,
    AccountProxyInterface $currentUser
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->classResolver = $classResolver;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Just delegate to the controller to do the work.
    $ctrl = $this->classResolver->getInstanceFromDefinition(ConsentAgreementController::class);
    return $ctrl->myAgreements($this->currentUser);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // Vary caching of this block per user.
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['user:' . $this->currentUser->id()]);
  }

}
