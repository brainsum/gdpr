<?php

namespace Drupal\gdpr_consent\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gdpr_consent\Entity\ConsentAgreement;

/**
 * Plugin implementation of the 'gdpr_consent_widget' widget.
 *
 * Provides the ability to attach a consent agreement to a form.
 *
 * @FieldWidget(
 *   id = "gdpr_consent_widget",
 *   label = @Translation("GDPR Consent"),
 *   description = @Translation("GDPR Consent"),
 *   field_types = {
 *     "gdpr_user_consent",
 *   },
 * )
 */
class ConsentWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    if (array_key_exists(0, $form['#parents']) && $form['#parents'][0] == 'default_value_input') {
      // Don't show as part of the 'Default Value' form when configuring field.
      return [];
    }

    $can_edit_anyones_consent = \Drupal::currentUser()->hasPermission('grant gdpr any consent');
    $can_edit_own_consent = \Drupal::currentUser()->hasPermission('grant gdpr own consent');
    // Consenting user and current user may not be the same.
    // For example, a staff member editing consent on behalf of a user who
    // calls the office.
    $current_user = \Drupal::currentUser();
    $consenting_user = $this->getConsentingUser($items);

    $agreement_id = $items->getFieldDefinition()->getSetting('target_id');

    if ($agreement_id == '') {
      // Don't display if an agreement hasn't
      // been configured for this field yet.
      return [];
    }

    if (!$can_edit_anyones_consent && $consenting_user->id() != $current_user->id()) {
      // Abort if the current user does not have permission
      // to edit other user's consent and we're editing another user.
      return [];
    }

    if (!$can_edit_own_consent && $consenting_user->id() == $current_user->id()) {
      // Abort if the current user cannot edit their own consent.
      return [];
    }

    $agreement = ConsentAgreement::load($agreement_id);
    $item = $items[$delta];

    $element['target_id'] = [
      '#type' => 'hidden',
      '#default_value' => $agreement_id,
    ];

    $element['target_revision_id'] = [
      '#type' => 'hidden',
      '#default_value' => isset($item->target_revision_id) ? $item->target_revision_id : $agreement->getRevisionId(),
    ];

    $element['agreed'] = [
      '#type' => 'checkbox',
      '#title' => $agreement->get('description')->value,
      '#description' => $agreement->get('long_description')->value,
      '#required' => $items->getFieldDefinition()->isRequired(),
      '#default_value' => isset($item->agreed) && $item->agreed == TRUE,
      '#attributes' => ['class' => ['gdpr_consent_agreement']],
      '#attached' => [
        'library' => [
          'gdpr_consent/gdpr_consent_display',
        ],
      ],
    ];

    // If we only require implicit agreement,
    // hide the checkbox and set it to true.
    if (!$agreement->requiresExplicitAcceptance()) {
      $element['agreed']['#title'] = '';
      $element['agreed']['#type'] = 'item';
      // Just render an empty span that the javascript can hook onto.
      $element['agreed']['#markup'] =
        '<span class="gdpr_consent_implicit">' . $agreement->get('description')->value . '</span>';
      $element['agreed']['#default_value'] = TRUE;
    }

    // Only show the notes field if the user has permission.
    if ($can_edit_anyones_consent) {
      $element['notes'] = [
        '#type' => 'textarea',
        '#title' => 'GDPR Consent Notes',
        '#required' => FALSE,
        '#default_value' => isset($item->notes) ? $item->notes : '',
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    for ($i = 0; $i < count($values); ++$i) {
      if (!isset($values[$i]['user_id_accepted'])) {
        $values[$i]['user_id_accepted'] = \Drupal::currentUser()->id();
      }
      if (!isset($values[$i]['date'])) {
        $values[$i]['date'] = date('Y-m-d H:i:s');
      }
    }
    return $values;
  }

  /**
   * Gets the user who the consent will be stored against.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field.
   *
   * @return \Drupal\user\Entity\User
   *   The user
   *
   * @throws \Exception
   */
  private function getConsentingUser(FieldItemListInterface $items) {
    $definition = $items->getFieldDefinition();
    /* @var \Drupal\gdpr_consent\ConsentUserResolver\ConsentUserResolverPluginManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.gdpr_consent_resolver');
    $resolver = $plugin_manager->getForEntityType($definition->getTargetEntityTypeId(), $definition->getTargetBundle());
    $user = $resolver->resolve($items->getEntity());
    return $user;
  }

}
