<?php

namespace Drupal\gdpr_consent\Plugin\Gdpr\ConsentUserResolver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\gdpr_consent\ConsentUserResolver\GdprConsentUserResolverInterface;

/**
 * Resolves user reference for a Profile entity.
 *
 * @GdprConsentUserResolver(
 *   id = "gdpr_consent_user_resolver",
 *   label = "GDPR Consent User Resolver",
 *   entityType = "user"
 * )
 * @package Drupal\gdpr_consent\Plugin\Gdpr\ConsentUserResolver
 */
class UserResolver implements GdprConsentUserResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(EntityInterface $entity) {
    return $entity;
  }

}
