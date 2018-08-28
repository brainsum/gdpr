<?php

namespace Drupal\gdpr_consent\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\gdpr_consent\ConsentUserResolver\ConsentUserResolverPluginManager;
use Drupal\gdpr_consent\Entity\ConsentAgreement;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class ConsentWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The GDPR Consent Resolver manager.
   *
   * @var \Drupal\gdpr_consent\ConsentUserResolver\ConsentUserResolverPluginManager
   */
  protected $gdprConsentResolverManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.gdpr_consent_resolver'),
      $container->get('current_user')
    );
  }

  /**
   * Constructs a ConsentWidget instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\gdpr_consent\ConsentUserResolver\ConsentUserResolverPluginManager $gdprConsentResolverManager
   *   The GDPR Consent Resolver manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    ConsentUserResolverPluginManager $gdprConsentResolverManager,
    AccountInterface $currentUser
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->gdprConsentResolverManager = $gdprConsentResolverManager;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    if (array_key_exists(0, $form['#parents']) && $form['#parents'][0] == 'default_value_input') {
      // Don't show as part of the 'Default Value' form when configuring field.
      return [];
    }

    $canEditAnyonesConsent = $this->currentUser->hasPermission('grant gdpr any consent');
    $canEditOwnConsent = $this->currentUser->hasPermission('grant gdpr own consent');
    // Consenting user and current user may not be the same.
    // For example, a staff member editing consent on behalf of a user who
    // calls the office.
    $consentingUser = $this->getConsentingUser($items);

    $agreementId = $items->getFieldDefinition()->getSetting('target_id');

    if ($agreementId === '') {
      // Don't display if an agreement hasn't
      // been configured for this field yet.
      return [];
    }

    // The current user is anonymous on the register page.
    if (!$this->currentUser->isAnonymous()) {
      if (!$canEditAnyonesConsent && $consentingUser->id() !== $this->currentUser->id()) {
        // Abort if the current user does not have permission
        // to edit other user's consent and we're editing another user.
        return [];
      }

      if (!$canEditOwnConsent && $consentingUser->id() === $this->currentUser->id()) {
        // Abort if the current user cannot edit their own consent.
        return [];
      }
    }

    $agreement = ConsentAgreement::load($agreementId);

    if (NULL === $agreement) {
      return [];
    }

    $item = $items[$delta];

    $element['target_id'] = [
      '#type' => 'hidden',
      '#default_value' => $agreementId,
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
      '#default_value' => isset($item->agreed) && ((bool) $item->agreed === TRUE),
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
    if ($canEditAnyonesConsent) {
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
    foreach ($values as &$value) {
      if (!isset($value['user_id_accepted'])) {
        $value['user_id_accepted'] = $this->currentUser->id();
      }
      if (!isset($value['date'])) {
        $value['date'] = \date('Y-m-d H:i:s');
      }
    }
    unset($value);

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
    $resolver = $this->gdprConsentResolverManager->getForEntityType($definition->getTargetEntityTypeId(), $definition->getTargetBundle());
    return $resolver->resolve($items->getEntity());
  }

}
