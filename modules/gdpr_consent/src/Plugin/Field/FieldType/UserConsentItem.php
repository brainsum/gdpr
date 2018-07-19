<?php

namespace Drupal\gdpr_consent\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\gdpr_consent\Entity\ConsentAgreement;
use Drupal\message\Entity\Message;

/**
 * Plugin implementation of the 'gdpr_user_consent' field type.
 *
 * @FieldType(
 *   id = "gdpr_user_consent",
 *   label = @Translation("GDPR Consent"),
 *   description = @Translation("Stores user consent for a particular agreement"),
 *   category = @Translation("GDPR"),
 *   default_widget = "gdpr_consent_widget",
 *   default_formatter = "gdpr_consent_formatter"
 * )
 */
class UserConsentItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return ['target_id' => '']
      + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['target_id'] = DataReferenceTargetDefinition::create('integer')
      ->setLabel('Target agreement ID')
      ->setSetting('unsigned', TRUE)
      ->setRequired(TRUE);

    $properties['target_revision_id'] = DataDefinition::create('integer')
      ->setLabel('Revision ID');

    $properties['agreed'] = DataDefinition::create('boolean')
      ->setLabel('Agreed');

    $properties['date'] = DataDefinition::create('datetime_iso8601')
      ->setLabel('Date stored');

    $properties['user_id'] = DataReferenceTargetDefinition::create('integer')
      ->setLabel('User ID');

    $properties['user_id_accepted'] = DataReferenceTargetDefinition::create('integer')
      ->setLabel('User ID Accepted');

    $properties['notes'] = DataReferenceTargetDefinition::create('string')
      ->setLabel('Notes');

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    $definition = $this->getFieldDefinition();

    /* @var \Drupal\gdpr_consent\ConsentUserResolver\ConsentUserResolverPluginManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.gdpr_consent_resolver');
    $resolver = $plugin_manager->getForEntityType($definition->getTargetEntityTypeId(), $definition->getTargetBundle());
    $user = $resolver->resolve($this->getEntity());

    if ($user != NULL) {
      $this->set('user_id', $user->id());
    }

    $should_log = FALSE;

    if (!$update) {
      // Always log on a create.
      $should_log = TRUE;
    }
    else {
      $field_name = $this->getFieldDefinition()->getName();
      $original_value = $this->getEntity()->original->{$field_name}->agreed;
      if ($original_value != $this->agreed) {
        $should_log = TRUE;
      }
    }

    if ($should_log) {
      $msg = Message::create(['template' => 'consent_agreement_accepted']);
      $msg->set('user', $this->user_id);
      $msg->set('user_accepted', $this->user_id_accepted);
      $msg->set('agreement', ['target_id' => $this->target_id, 'target_revision_id' => $this->target_revision_id]);
      $msg->set('notes', $this->notes);
      $msg->set('agreed', $this->agreed);
      $msg->save();
    }

    if ($user != NULL) {
      return TRUE;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {

    $agreement_ids = \Drupal::entityQuery('gdpr_consent_agreement')
      ->condition('status', 1)
      ->sort('title')
      ->execute();

    $agreements = ConsentAgreement::loadMultiple($agreement_ids);

    $element = [];

    $element['target_id'] = [
      '#type' => 'select',
      '#title' => 'Agreement',
      '#required' => TRUE,
      '#options' => ['' => 'Please select'] + $agreements,
      '#default_value' => $this->getSetting('target_id'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'indexes' => [
        'target_id' => ['target_id'],
      ],
    ];

    $schema['columns']['target_id'] = [
      'description' => 'The ID of the target entity.',
      'type' => 'int',
      'unsigned' => TRUE,
    ];

    $schema['columns']['target_revision_id'] = [
      'description' => 'The Revision ID of the target entity.',
      'type' => 'int',
    ];

    $schema['columns']['agreed'] = [
      'description' => 'Whether the user has agreed.',
      'type' => 'int',
      'size' => 'tiny',
      'default' => 0,
    ];

    $schema['columns']['user_id'] = [
      'description' => 'ID of the user who has accepted.',
      'type' => 'int',
    ];

    $schema['columns']['date'] = [
      'description' => 'Time that the user agreed.',
      'type' => 'varchar',
      'length' => 20,
    ];

    $schema['columns']['user_id_accepted'] = [
      'description' => 'ID of the user who recorded the acceptance',
      'type' => 'int',
    ];

    $schema['columns']['notes'] = [
      'description' => 'Additional notes on the acceptance',
      'type' => 'varchar',
      'length' => '255',
    ];
    return $schema;
  }

}
