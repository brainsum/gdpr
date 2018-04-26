<?php

namespace Drupal\gdpr_consent\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\gdpr_consent\Controller\ConsentAgreementController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to view a contact dashboard summary.
 *
 * @Block(
 *   id = "gdpr_agreements_block",
 *   category = @Translation("Dashboard Blocks"),
 *   deriver = "Drupal\gdpr_consent\Plugin\Deriver\GdprMyAgreementsDeriver",
 *   dashboard_block = TRUE,
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
   * {@inheritdoc}
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClassResolverInterface $class_resolver) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('class_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $user = $this->getContextValue('user');
    // Just delegate to the controller to do the work.
    $ctrl = $this->classResolver->getInstanceFromDefinition(ConsentAgreementController::class);
    return $ctrl->myAgreements($user->id());
  }

}
