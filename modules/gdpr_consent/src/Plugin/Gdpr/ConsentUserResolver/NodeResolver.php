<?php

namespace Drupal\gdpr_consent\Plugin\Gdpr\ConsentUserResolver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\gdpr_consent\ConsentUserResolver\GdprConsentUserResolverInterface;

/**
 * Resolves user reference for a Node entity.
 *
 * @GdprConsentUserResolver(
 *   id = "gdpr_consent_node_resolver",
 *   label = "GDPR Consent Node Resolver",
 *   entityType = "node"
 * )
 * @package Drupal\gdpr_consent\Plugin\Gdpr\ConsentUserResolver
 */
class NodeResolver implements GdprConsentUserResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(EntityInterface $entity) {
    return $entity->uid->entity;
  }

}
