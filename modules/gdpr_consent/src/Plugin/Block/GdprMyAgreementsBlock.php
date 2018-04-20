<?php

namespace Drupal\gdpr_consent\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\gdpr_consent\Controller\ConsentAgreementController;

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
class GdprMyAgreementsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $user = $this->getContextValue('user');
    // Just delegate to the controller to do the work.
    $ctrl = new ConsentAgreementController(\Drupal::getContainer()->get('entity_field.manager'));
    return $ctrl->myAgreements($user->id());
  }
}