<?php

namespace Drupal\gdpr_view_export_log\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the Export Audit entity.
 *
 * @ingroup gdpr_view_export_log
 *
 * @ContentEntityType(
 *   id = "gdpr_view_export_audit",
 *   label = @Translation("Export Log"),
 *   base_table = "gdpr_view_export_audit",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   admin_permission = "create gdpr export audits",
 *   links={
 *     "add-form" = "/admin/gdpr/export/add",
 *     "delete-form" = "/admin/gdpr/export/{gdpr_view_export_audit}/delete",
 *     "collection" = "/admin/gdpr/exports",
 *   },
 *   handlers={
 *     "list_builder" = "Drupal\gdpr_view_export_log\Entity\ExportAuditListBuilder",
 *     "form" = {
 *       "default" = "Drupal\gdpr_view_export_log\Form\AuditForm",
 *       "add" = "Drupal\gdpr_view_export_log\Form\AuditForm",
 *       "delete" = "Drupal\gdpr_view_export_log\Form\AuditDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *   }
 * )
 */
class ExportAudit extends ContentEntityBase {
  public static function exportDisplayHandler() {
    return 'Drupal\views_data_export\Plugin\views\display\DataExport';
  }


  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['location'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Location'))
      ->setDescription(t('Where will this export be stored? (For example, a user\'s PC)'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'textfield',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);


    $fields['reason'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Reason'))
      ->setDescription(t('The reason for the export'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'textfield_long',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['length'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Length'))
      ->setDescription(t('Length (in a days) that the export should live for.'))
      ->setDefaultValue(90)
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);



    $fields['owner'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the audit entry.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        // 'label' => 'hidden',
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

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['filename'] = BaseFieldDefinition::create('string')
      ->setLabel(t('File name'))
      ->setDescription(t('The name of the file'));

    $fields['view'] = BaseFieldDefinition::create('string')
      ->setLabel(t('View'))
      ->setDescription(t('The name of the view'));

    return $fields;
  }

}