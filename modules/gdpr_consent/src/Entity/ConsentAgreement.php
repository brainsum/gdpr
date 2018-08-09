<?php

namespace Drupal\gdpr_consent\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\UserInterface;

/**
 * Defines the ConsentAgreement entity.
 *
 * @ingroup gdpr_consent
 *
 * @ContentEntityType(
 *   id = "gdpr_consent_agreement",
 *   label = @Translation("Consent Agreement"),
 *   description = @Translation("Consent Agreement"),
 *   base_table = "gdpr_consent_agreement",
 *   data_table = "gdpr_consent_agreement_field_data",
 *   revision_table = "gdpr_consent_agreement_revision",
 *   translatable = TRUE,
 *   handlers = {
 *     "storage" = "Drupal\gdpr_consent\ConsentAgreementStorage",
 *     "list_builder" = "Drupal\gdpr_consent\Entity\ConsentAgreementListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\gdpr_consent\ConsentAgreementHtmlRouteProvider"
 *     },
 *     "form" = {
 *       "default" = "Drupal\gdpr_consent\Form\ConsentAgreementForm",
 *       "add" = "Drupal\gdpr_consent\Form\ConsentAgreementForm",
 *       "edit" = "Drupal\gdpr_consent\Form\ConsentAgreementForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *     "revision" = "revision_id",
 *   },
 *   admin_permission = "manage gdpr agreements",
 *   links = {
 *     "canonical" = "/admin/gdpr/agreements/{gdpr_consent_agreement}",
 *     "add-form" = "/admin/gdpr/agreements/add",
 *     "edit-form" = "/admin/gdpr/agreements/{gdpr_consent_agreement}/edit",
 *     "delete-form" = "/admin/gdpr/agreements/{gdpr_consent_agreement}/delete",
 *     "version-history" = "/admin/gdpr/agreements/{gdpr_consent_agreement}/revisions",
 *     "revision" = "/admin/gdpr/agreements/{gdpr_consent_agreement}/revisions/{gdpr_consent_agreement_revision}/view",
 *     "revision_revert" = "/admin/gdpr/agreements/{gdpr_consent_agreement}/revisions/{gdpr_consent_agreement_revision}/revert",
 *     "revision_delete" = "/admin/gdpr/agreements/{gdpr_consent_agreement}/revisions/{gdpr_consent_agreement_revision}/delete",
 *     "collection" = "/admin/gdpr/agreements",
 *   },
 * )
 */
class ConsentAgreement extends RevisionableContentEntityBase implements ConsentAgreementInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uriRouteParameters = parent::urlRouteParameters($rel);

    if ($this instanceof RevisionableInterface) {
      if ($rel === 'revision_revert') {
        $uriRouteParameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
      }
      elseif ($rel === 'revision_delete') {
        $uriRouteParameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
      }
    }

    return $uriRouteParameters;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach (\array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }
    }

    // If no revision author has been set explicitly,
    // make the ConsentAgreement owner the
    // revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }
  }

  /**
   * Check if agreement is explicit.
   *
   * @return bool
   *   Whether the agreement is explicit.
   */
  public function requiresExplicitAcceptance() {
    return $this->get('mode')->value == 'explicit';
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
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
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * Return the title.
   *
   * @return string
   *   The title.
   */
  public function getTitle() {
    return (string) $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDescription(t('Agreement title.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'textfield',
      ])
      ->setDisplayOptions('form', [
        'type' => 'textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['mode'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Agreement type'))
      ->setRevisionable(TRUE)
      ->setDescription(t('Whether consent is implicit or explicit. Set to "Explicit" if the user needs to explicitly agree, otherwise "Implicit".'))
      ->setDefaultValue('explicit')
      ->setSetting('allowed_values_function', [static::class, 'getModes'])
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'select',
      ])
      ->setDisplayOptions('form', [
        'type' => 'select',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Description'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDescription(t('Text displayed to the user on the form'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'textfield',
      ])
      ->setDisplayOptions('form', [
        'type' => 'textfield',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['long_description'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Long description'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDescription(t('Text shown when the user clicks for more details.'))
      ->setDisplayOptions('view', [
        'type' => 'textarea',
      ])
      ->setDisplayOptions('form', [
        'type' => 'textarea',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Notes'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDescription(t('This should contain the rationale behind the agreement.'))
      ->setDisplayOptions('view', [
        'type' => 'textarea',
      ])
      ->setDisplayOptions('form', [
        'type' => 'textarea',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Consent Agreement is published.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Revision translation affected'))
      ->setDescription(t('Indicates if the last edit of a translation belongs to current revision.'))
      ->setReadOnly(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Consent Agreement entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'author',
      ])
      ->setDisplayOptions('form', [
        'type' => 'hidden',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'region' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'type' => 'language_select',
        'weight' => 2,
      ]);

    return $fields;
  }

  /**
   * Get the available consent modes.
   *
   * @return array
   *   Array of consent modes.
   */
  public static function getModes() {
    return [
      'implicit' => t('Implicit'),
      'explicit' => t('Explicit'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return $this->getTitle();
  }

}
