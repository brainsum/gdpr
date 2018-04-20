<?php

namespace Drupal\gdpr_consent\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Context\ContextDefinition;

/**
 * Defines the deriver for the My Agreements block.
 */
class GdprMyAgreementsDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives['contacts_dashboard'] = $base_plugin_definition;
    $this->derivatives['contacts_dashboard']['admin_label'] = 'GDPR Agreements Accepted';
    $this->derivatives['contacts_dashboard']['context'] = [
      'user' => new ContextDefinition('entity:user', 'User', FALSE),
    ];

    return $this->derivatives;
  }

}
