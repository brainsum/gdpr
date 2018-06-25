<?php

namespace Drupal\gdpr_tasks\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Task entity.
 *
 * @ingroup gdpr_tasks
 *
 * @ContentEntityType(
 *   id = "gdpr_task",
 *   label = @Translation("Task"),
 *   bundle_label = @Translation("Task type"),
 *   label_collection = @Translation("Task list"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\gdpr_tasks\TaskListBuilder",
 *     "views_data" = "Drupal\gdpr_tasks\Entity\TaskViewsData",
 *     "form" = {
 *       "default" = "Drupal\gdpr_tasks\Form\TaskForm",
 *       "process" = "Drupal\gdpr_tasks\Form\TaskActionsForm",
 *       "delete" = "Drupal\gdpr_tasks\Form\TaskDeleteForm",
 *     },
 *     "access" = "Drupal\gdpr_tasks\TaskAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "gdpr_task",
 *   admin_permission = "administer task entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/gdpr/tasks/{gdpr_task}",
 *     "delete-form" = "/admin/gdpr/tasks/{gdpr_task}/delete",
 *     "collection" = "/admin/gdpr/tasks",
 *   },
 *   bundle_entity_type = "gdpr_task_type",
 *   field_ui_base_route = "entity.gdpr_task_type.edit_form"
 * )
 */
class Task extends ContentEntityBase implements TaskInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'requested_by' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setProcessedBy(UserInterface $account) {
    $this->set('processed_by', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setProcessedById($uid) {
    $this->set('processed_by', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessedBy() {
    return $this->get('processed_by')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getProcessedById() {
    return $this->get('processed_by')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return t('Task @id', ['@id' => $this->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->status->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatusLabel() {
    $statuses = $this->getStatuses();
    $value = $this->getStatus();
    if (isset($statuses[$value])) {
      $value = $statuses[$value];
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user whose data should be processed.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setRequired(TRUE)
      ->setDefaultValue('requested')
      ->setSetting('allowed_values_function', [static::class, 'getStatuses'])
      ->setDisplayOptions('form', [
        'type' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['complete'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the task was completed.'));

    $fields['processed_by'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Processed by'))
      ->setDescription(t('The user who processed this task.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'author',
        'weight' => 5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'hidden',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['requested_by'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Requested by'))
      ->setDescription(t('The user who requested this task.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'author',
        'weight' => 6,
      ])
      ->setDisplayOptions('form', [
        'type' => 'hidden',
        'weight' => 6,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setDescription(t('Notes with this request'))
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'type' => 'textfield',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * Gets the allowed values for the 'billing_countries' base field.
   *
   * @return array
   *   The allowed values.
   */
  public static function getStatuses() {
    return [
      'requested' => t('Requested'),
      'building' => t('Building data'),
      'reviewing' => t('Needs review'),
      'processed' => t('Processed'),
      'closed' => t('Closed'),
    ];
  }

}
