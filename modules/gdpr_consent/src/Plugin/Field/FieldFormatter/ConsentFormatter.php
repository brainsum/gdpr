<?php

namespace Drupal\gdpr_consent\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the gdpr_consent_formatter formatter.
 *
 * @FieldFormatter(
 *   id = "gdpr_consent_formatter",
 *   label = @Translation("GDPR Consent Formatter"),
 *   field_types = {
 *    "gdpr_user_consent"
 *   }
 * )
 */
class ConsentFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $output = [];

    $storage = \Drupal::entityTypeManager()
      ->getStorage('gdpr_consent_agreement');

    foreach ($items as $delta => $item) {
      $agreement = $storage->loadRevision($item->target_revision_id);

      $output[$delta] = [
        'name' => [
          '#markup' => $agreement->toLink($agreement->title->value, 'revision')->toString() . ' on ' . $item->date,
        ],
      ];

    }

    return $output;
  }

}
