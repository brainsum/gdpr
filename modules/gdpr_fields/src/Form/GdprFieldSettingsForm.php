<?php

namespace Drupal\gdpr_fields\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\gdpr_fields\Entity\GdprField;
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
   * @param int $relationship
   *   Relationship setting.
   * @param string $sars_filename
   *   Filename to store data from this relationship in subject access requests.
   *
   * @return \Drupal\gdpr_fields\Entity\GdprFieldConfigEntity
   *   The config entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  private function setConfig($entity_type, $bundle, $field_name, $enabled, $rta, $rtf, $anonymizer, $notes, $relationship, $sars_filename) {
    $field = new GdprField([
      'bundle' => $bundle,
      'name' => $field_name,
      'entity_type_id' => $entity_type,
    ]);

    $field->setEnabled($enabled)
      ->setRta($rta)
      ->setRtf($rtf)
      ->setAnonymizer($anonymizer)
      ->setNotes($notes)
      ->setRelationship($relationship)
      ->setSarsFilename($sars_filename);

    $storage = $this->entityTypeManager->getStorage('gdpr_fields_config');
    /* @var \Drupal\gdpr_fields\Entity\GdprFieldConfigEntity $config */
    $config = $storage->load($entity_type);

    if (!$config) {
      $config = $storage->create(['id' => $entity_type]);
    }

    $config->setField($field);

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
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
   * @return array
   *   Form.
   *
   * @see gdpr_fields_form_field_config_edit_form_submit
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function buildFormFields(array &$form, $entity_type = NULL, $bundle_name = NULL, $field_name = NULL) {
    $config = static::getConfig($entity_type, $bundle_name, $field_name);

    /* @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
    /* @var \Drupal\anonymizer\Anonymizer\AnonymizerFactory $anonymizer_factory */
    $entity_type_manager = \Drupal::entityTypeManager();
    $field_manager = \Drupal::service('entity_field.manager');
    $anonymizer_factory = \Drupal::service('anonymizer.anonymizer_factory');
    $anonymizer_definitions = $anonymizer_factory->getDefinitions();
    $entity_definition = $entity_type_manager->getDefinition($entity_type);
    $field_definition = $field_manager->getFieldDefinitions($entity_type, $bundle_name)[$field_name];

    // Exclude uuid/bundle.
    if ($entity_definition->getKey('uuid') == $field_name || $entity_definition->getKey('bundle') == $field_name) {
      return [];
    }

    $form['gdpr_enabled'] = [
      '#type' => 'checkbox',
      '#title' => t('This is a GDPR field'),
      '#default_value' => $config->enabled,
    ];

    $form['gdpr_relationship'] = [
      '#type' => 'value',
      '#value' => GdprField::RELATIONSHIP_DISABLED,
    ];

    $form['gdpr_sars_filename'] = [
      '#type' => 'value',
      '#value' => $config->sarsFilename,
    ];

    if ($field_definition->getType() == 'entity_reference') {
      $inner_entity_type = $field_definition->getSetting('target_type');
      $inner_entity_definition = $entity_type_manager->getDefinition($inner_entity_type);

      $form['gdpr_relationship'] = [
        '#type' => 'select',
        '#default_value' => $config->relationship,
        '#options' => [
          GdprField::RELATIONSHIP_DISABLED => new TranslatableMarkup('Do not follow this relationship.'),
          GdprField::RELATIONSHIP_FOLLOW => new TranslatableMarkup('This %entity_type_label owns the referenced %target_entity_type_label (Relationship will be followed)', ['%entity_type_label' => $entity_definition->getLabel(), '%target_entity_type_label' => $inner_entity_definition->getLabel()]),
          GdprField::RELATIONSHIP_OWNER => new TranslatableMarkup('This %entity_type_label is owned by the referenced %target_entity_type_label', ['%entity_type_label' => $entity_definition->getLabel(), '%target_entity_type_label' => $inner_entity_definition->getLabel()]),
        ],
        '#title' => t('Relationship Handling'),
        '#description' => new TranslatableMarkup('Owned entities are included in any task which contains the owner.', [
          '%type' => $inner_entity_definition->getLabel(),
        ]),
        '#states' => [
          'visible' => [
            ':input[name="gdpr_enabled"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];
    }

    $form['gdpr_rta'] = [
      '#type' => 'select',
      '#weight' => 10,
      '#title' => t('Right to access'),
      '#options' => GdprField::rtaOptions(),
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
      '#weight' => 20,
      '#type' => 'select',
      '#title' => t('Right to be forgotten'),
      '#options' => GdprField::rtfOptions(),
      '#default_value' => $config->rtf,
      '#states' => [
        'visible' => [
          ':input[name="gdpr_enabled"]' => [
            'checked' => TRUE,
          ],
        ],
      ],
    ];

    $error_message = NULL;
    if ($entity_definition->getKey('id') == $field_name) {
      // If this is the entity's ID, treat the removal as remove the entire
      // entity.
      unset($form['gdpr_rtf']['#options']['anonymise']);
      $form['gdpr_rtf']['#options']['remove'] = new TranslatableMarkup('Delete entire entity');

      // Define target filename for this bundle.
      // @todo: Move to a form alter in gdpr_tasks.
      // @todo: Add <inherit> option to inherit owned entity filename.
      $form['gdpr_sars_filename'] = [
        '#type' => 'textfield',
        '#title' => t('Right to access filename'),
        '#description' => t('Specify the filename for the owned entity to go in. Use %inherit to keep the related entity in the same file.', []),
        // Default to the entity type.
        '#default_value' => $config->sarsFilename,
        '#field_suffix' => '.csv',
        '#size' => 20,
        // Between RTA and RTF.
        '#weight' => 15,
        '#required' => TRUE,
        '#states' => [
          'visible' => [
            ':input[name="gdpr_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
    // Otherwise check if this can be removed.
    elseif (!$config->propertyCanBeRemoved($field_definition, $error_message)) {
      unset($form['gdpr_rtf']['#options']['remove']);
      $form['gdpr_rtf_disabled'] = [
        '#type' => 'item',
        '#markup' => new TranslatableMarkup('This field cannot be removed, only anonymised.'),
        '#description' => $error_message,
      ];
    }

    // Force removal to 'no' for computed properties.
    if ($field_definition->isComputed()) {
      $form['gdpr_rtf']['#default_value'] = 'no';
      $form['gdpr_rtf']['#disabled'] = TRUE;
      $form['gdpr_rtf']['#description'] = t('*This is a computed field and cannot be removed.');
    }

    $sanitizer_options = array_map(function ($s) {
        return $s['label'];
    }, $anonymizer_definitions);

    $form['gdpr_anonymizer'] = [
      '#weight' => 30,
      '#type' => 'select',
      '#title' => t('Anonymizer to use'),
      '#options' => $sanitizer_options,
      '#default_value' => $config->anonymizer,
      '#states' => [
        'visible' => [
          ':input[name="gdpr_enabled"]' => ['checked' => TRUE],
          ':input[name="gdpr_rtf"]' => ['value' => 'anonymize'],
        ],
        'required' => [
          ':input[name="gdpr_enabled"]' => ['checked' => TRUE],
          ':input[name="gdpr_rtf"]' => ['value' => 'anonymize'],
        ],
      ],
    ];

    $form['gdpr_notes'] = [
      '#weight' => 40,
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
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getTriggeringElement()['#name'] == 'Cancel') {
      $form_state->setRedirect('gdpr_fields.fields_list');
      return;
    }

    $config = $this->setConfig(
      $form_state->getValue('entity_type'),
      $form_state->getValue('bundle'),
      $form_state->getValue('field_name'),
      $form_state->getValue('gdpr_enabled'),
      $form_state->getValue('gdpr_rta'),
      $form_state->getValue('gdpr_rtf'),
      $form_state->getValue('gdpr_anonymizer'),
      $form_state->getValue('gdpr_notes'),
      $form_state->getValue('gdpr_relationship'),
      $form_state->getValue('gdpr_sars_filename')
    );

    $config->save();
    $this->messenger->addMessage('Field settings saved.');
    $form_state->setRedirect('gdpr_fields.fields_list');
  }

}
