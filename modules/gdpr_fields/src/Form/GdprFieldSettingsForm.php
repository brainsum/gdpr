<?php

namespace Drupal\gdpr_fields\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * GDPR Field settings.
 */
class GdprFieldSettingsForm extends FormBase {

  /**
   * The entity field manager used to work with fields.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Entity type manager used to work with types.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * GdprFieldSettingsForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * Gets the configuration for an entity/bundle/field.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Bundle.
   * @param string $field_name
   *   Field.
   *
   * @return \Drupal\gdpr_fields\Entity\GdprField
   *   Field metadata.
   */
  private static function getConfig($entity_type, $bundle, $field_name) {
    $config = GdprFieldConfigEntity::load($entity_type);
    if (NULL === $config) {
      $config = GdprFieldConfigEntity::create(['id' => $entity_type]);
    }
    $field_config = $config->getField($bundle, $field_name);
    return $field_config;
  }

  /**
   * Sets the GDPR settings for a field.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Bundle.
   * @param string $field_name
   *   Field.
   * @param bool $enabled
   *   Whether GDPR is enabled for this field.
   * @param string $rta
   *   Right to Access setting.
   * @param string $rtf
   *   Right to be forgotten.
   * @param string $anonymizer
   *   Anonymizer to use.
   * @param string $notes
   *   Notes.
   *
   * @return \Drupal\gdpr_fields\Entity\GdprFieldConfigEntity
   *   The config entity.
   */
  private static function setConfig($entity_type, $bundle, $field_name, $enabled, $rta, $rtf, $anonymizer, $notes) {
    $config = GdprFieldConfigEntity::load($entity_type);
    if (NULL === $config) {
      $config = GdprFieldConfigEntity::create(['id' => $entity_type]);
    }
    $config->setField($bundle, $field_name, [
      'enabled' => $enabled,
      'rta' => $rta,
      'rtf' => $rtf,
      'anonymizer' => $anonymizer,
      'notes' => $notes,
    ]);
    return $config;
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'gdpr_fields_edit_field_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle_name
   *   The entity bundle.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type = NULL, $bundle_name = NULL, $field_name = NULL) {
    if (empty($entity_type) || empty($bundle_name) || empty($field_name)) {
      $this->messenger()->addWarning('Could not load field.');
      return [];
    }

    $field_defs = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle_name);

    if (!array_key_exists($field_name, $field_defs)) {
      $this->messenger()->addWarning("The field $field_name does not exist.");
      return [];
    }
    $field_def = $field_defs[$field_name];
    $form['#title'] = 'GDPR Settings for ' . $field_def->getLabel();

    static::buildFormFields($form, $entity_type, $bundle_name, $field_name);

    $form['entity_type'] = [
      '#type' => 'hidden',
      '#default_value' => $entity_type,
    ];

    $form['bundle'] = [
      '#type' => 'hidden',
      '#default_value' => $bundle_name,
    ];

    $form['field_name'] = [
      '#type' => 'hidden',
      '#default_value' => $field_name,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
        '#name' => 'Save',
      ],
      'submit_cancel' => [
        '#type' => 'submit',
        '#weight' => 99,
        '#value' => $this->t('Cancel'),
        '#name' => 'Cancel',
        '#limit_validation_errors' => [],
      ],
    ];

    return $form;
  }

  /**
   * Builds the form fields for GDPR settings.
   *
   * This is in a separate method so it can also be attached to the regular
   * field settings page by hook.
   *
   * @param array $form
   *   Form.
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle_name
   *   Bundle.
   * @param string $field_name
   *   Field.
   *
   * @see gdpr_fields_form_field_config_edit_form_submit
   */
  public static function buildFormFields(array &$form, $entity_type = NULL, $bundle_name = NULL, $field_name = NULL) {
    $config = static::getConfig($entity_type, $bundle_name, $field_name);

    /* @var \Drupal\anonymizer\Anonymizer\AnonymizerFactory $anonymizer_factory */
    $anonymizer_factory = \Drupal::service('anonymizer.anonymizer_factory');
    $anonymizer_definitions = $anonymizer_factory->getDefinitions();

    $form['gdpr_enabled'] = [
      '#type' => 'checkbox',
      '#title' => t('This is a GDPR field'),
      '#default_value' => $config->enabled,
    ];

    $form['gdpr_rta'] = [
      '#type' => 'select',
      '#title' => t('Right to access'),
      '#options' => [
        'inc' => 'Included',
        'maybe' => 'Maybe',
        'no' => 'Not Included',
      ],
      '#default_value' => $config->rta,
      '#states' => [
        'visible' => [
          ':input[name="gdpr_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $form['gdpr_rtf'] = [
      '#type' => 'select',
      '#title' => t('Right to be forgotten'),
      '#options' => [
        'anonymize' => 'Anonymize',
        'remove' => 'Remove',
        'maybe' => 'Maybe',
        'no' => 'Not Included',
      ],
      '#default_value' => $config->rtf,
      '#states' => [
        'visible' => [
          ':input[name="gdpr_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $anonymizer_options = ['' => ''] + array_map(function ($s) {
        return $s['label'];
    }, $anonymizer_definitions);

    $form['gdpr_anonymizer'] = [
      '#type' => 'select',
      '#title' => t('Anonymizer to use'),
      '#options' => $anonymizer_options,
      '#default_value' => $config->anonymizer,
      '#states' => [
        'visible' => [
          ':input[name="gdpr_enabled"]' => ['checked' => TRUE],
          ':input[name="gdpr_rtf"]' => ['value' => 'anonymize'],
        ],
      ],
    ];

    $form['gdpr_notes'] = [
      '#type' => 'textarea',
      '#title' => 'Notes',
      '#default_value' => $config->notes,
      '#states' => [
        'visible' => [
          ':input[name="gdpr_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] == 'Cancel') {
      $form_state->setRedirect('gdpr_fields.fields_list');
      return;
    }

    $config = static::setConfig(
      $form_state->getValue('entity_type'),
      $form_state->getValue('bundle'),
      $form_state->getValue('field_name'),
      $form_state->getValue('gdpr_enabled'),
      $form_state->getValue('gdpr_rta'),
      $form_state->getValue('gdpr_rtf'),
      $form_state->getValue('gdpr_anonymizer'),
      $form_state->getValue('gdpr_notes')
    );

    $config->save();
    $this->messenger->addMessage('Field settings saved.');
    $form_state->setRedirect('gdpr_fields.fields_list');
  }

}
