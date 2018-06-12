<?php

namespace Drupal\gdpr_tasks;

use Drupal\anonymizer\Anonymizer\AnonymizerFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;
use Drupal\gdpr_fields\GDPRCollector;
use Drupal\gdpr_tasks\Entity\TaskInterface;
use Drupal\gdpr_tasks\Form\RemovalSettingsForm;

/**
 * Anonymizes or removes field values for GDPR.
 */
class Anonymizer {

  use StringTranslationTrait;

  /**
   * Collector used to retrieve properties to anonymize.
   *
   * @var \Drupal\gdpr_fields\GDPRCollector
   */
  private $collector;

  /**
   * Database instance for the request.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * Entity Type manager used to retrieve field storage info.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Drupal module handler for hooks.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private $currentUser;

  /**
   * Factory used to retrieve anonymizer to use on a particular field.
   *
   * @var \Drupal\anonymizer\Anonymizer\AnonymizerFactory
   */
  private $anonymizerFactory;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Anonymizer constructor.
   *
   * @param \Drupal\gdpr_fields\GDPRCollector $collector
   *   Fields collector.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\anonymizer\Anonymizer\AnonymizerFactory $anonymizerFactory
   *   The anonymizer plugin factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    GDPRCollector $collector,
    Connection $database,
    EntityTypeManagerInterface $entityTypeManager,
    ModuleHandlerInterface $moduleHandler,
    AccountProxyInterface $currentUser,
    AnonymizerFactory $anonymizerFactory,
    ConfigFactoryInterface $configFactory
  ) {
    $this->collector = $collector;
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
    $this->currentUser = $currentUser;
    $this->anonymizerFactory = $anonymizerFactory;
    $this->configFactory = $configFactory;
  }

  /**
   * Runs anonymization routines against a user.
   *
   * @param \Drupal\gdpr_tasks\Entity\TaskInterface $task
   *   The current task being executed.
   *
   * @return array
   *   Returns array containing any error messages.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function run(TaskInterface $task) {
    // Make sure we load a fresh copy of the entity (bypassing the cache)
    // so we don't end up affecting any other references to the entity.
    $user = $task->getOwner();

    $errors = [];
    $entities = [];
    $successes = [];
    $failures = [];
    $log = [];

    if (!$this->checkExportDirectoryExists()) {
      $errors[] = $this->t('An export directory has not been set. Please set this under Configuration -> GDPR -> Right to be Forgotten');
    }

    $this->collector->getValueEntities($entities, 'user', $user);

    foreach ($entities as $bundles) {
      foreach ($bundles as $bundleEntity) {
        // Re-load a fresh copy of the bundle entity from storage so we don't
        // end up modifying any other references to the entity in memory.
        $bundleEntity = $this->entityTypeManager->getStorage($bundleEntity->getEntityTypeId())
          ->loadUnchanged($bundleEntity->id());

        $entitySuccess = TRUE;

        try {
          $fieldsToProcess = $this->getFieldsToProcess($bundleEntity);
        }
        catch (\Exception $e) {
          $fieldsToProcess = [];
          $entitySuccess = FALSE;
          // @todo: Log.
        }

        foreach ($fieldsToProcess as $fieldInfo) {
          /** @var \Drupal\Core\Field\FieldItemListInterface $field */
          $field = $fieldInfo['field'];
          $mode = $fieldInfo['mode'];

          $success = TRUE;
          $msg = NULL;
          $anonymizer = '';

          if ($mode === 'anonymize') {
            list($success, $msg, $anonymizer) = $this->anonymize($field, $bundleEntity);
          }
          elseif ($mode === 'remove') {
            list($success, $msg) = $this->remove($field);
          }

          if ($success === TRUE) {
            $log[] = [
              'entity_id' => $bundleEntity->id(),
              'entity_type' => $bundleEntity->getEntityTypeId() . '.' . $bundleEntity->bundle(),
              'field_name' => $field->getName(),
              'action' => $mode,
              'anonymizer' => $anonymizer,
            ];
          }
          else {
            // Could not anonymize/remove field. Record to errors list.
            // Prevent entity from being saved.
            $entitySuccess = FALSE;
            $errors[] = $msg;
          }
        }

        if ($entitySuccess) {
          $successes[] = $bundleEntity;
        }
        else {
          $failures[] = $bundleEntity;
        }
      }
    }

    $task->get('removal_log')->setValue($log);

    if (\count($failures) === 0) {
      $transaction = $this->database->startTransaction();

      try {
        /* @var EntityInterface $entity */
        foreach ($successes as $entity) {
          $entity->save();
        }
        // Re-fetch the user so we see any changes that were made.
        $user = $this->refetchUser($task->getOwnerId());
        $user->block();
        $user->save();

        $this->writeLogToFile($task, $log);
      }
      catch (\Exception $e) {
        $transaction->rollBack();
        $errors[] = $e->getMessage();
      }
    }

    return $errors;
  }

  /**
   * Removes the field value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The current field to process.
   *
   * @return array
   *   First element is success boolean, second element is the error message.
   */
  private function remove(FieldItemListInterface $field) {
    try {
      $field->setValue(NULL);
      return [TRUE, NULL];
    }
    catch (ReadOnlyException $e) {
      return [FALSE, $e->getMessage()];
    }
  }

  /**
   * Runs anonymize functionality against a field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to anonymize.
   * @param \Drupal\Core\Entity\EntityInterface $bundleEntity
   *   The parent entity.
   *
   * @return array
   *   First element is success boolean, second element is the error message.
   */
  private function anonymize(FieldItemListInterface $field, EntityInterface $bundleEntity) {
    $anonymizer_id = $this->getAnonymizerId($field, $bundleEntity);

    if (!$anonymizer_id) {
      return [
        FALSE,
        "Could not anonymize field {$field->getName()}. Please consider changing this field from 'anonymize' to 'remove', or register a custom anonymizer.",
        NULL,
      ];
    }

    try {
      $anonymizer = $this->anonymizerFactory->get($anonymizer_id);
      $field->setValue($anonymizer->anonymize($field->value, $field));
      return [TRUE, NULL, $anonymizer_id];
    }
    catch (\Exception $e) {
      return [FALSE, $e->getMessage(), NULL];
    }

  }

  /**
   * Gets the ID of the anonymizer plugin to use on this field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to anonymize.
   * @param \Drupal\Core\Entity\EntityInterface $bundleEntity
   *   The parent entity.
   *
   * @return string
   *   The anonymizer ID or null.
   */
  private function getAnonymizerId(FieldItemListInterface $field, EntityInterface $bundleEntity) {
    // First check if this field has a anonymizer defined.
    $config = GdprFieldConfigEntity::load($bundleEntity->getEntityTypeId());
    $fieldConfig = $config->getField($bundleEntity->bundle(), $field->getName());
    $anonymizer = $fieldConfig->anonymizer;
    $fieldDefinition = $field->getFieldDefinition();
    $type = $fieldDefinition->getType();

    if (!$anonymizer) {
      // No anonymizer defined directly on the field.
      // Instead try and get one for the datatype.
      $anonymizers = [
        'string' => 'gdpr_text_anonymizer',
        'datetime' => 'gdpr_date_anonymizer',
      ];

      $this->moduleHandler->alter('gdpr_type_anonymizers', $anonymizers);
      $anonymizer = $anonymizers[$type];
    }
    return $anonymizer;
  }

  /**
   * Gets fields to anonymize/remove.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to anonymize.
   *
   * @return array
   *   Array containing metadata about the entity.
   *   Elements are entity_type, bundle, field and mode.
   *
   * @throws \Exception
   */
  private function getFieldsToProcess(EntityInterface $entity) {
    $bundleId = $entity->bundle();
    $entityTypeId = $entity->getEntityTypeId();
    $config = GdprFieldConfigEntity::load($entityTypeId);

    if (NULL === $config) {
      throw new \Exception('The GDPR field config could not be loaded for the "' . $entityTypeId . '" entity type.');
    }

    // Get fields for entity.
    $fields = [];
    foreach ($entity as $field) {
      /** @var \Drupal\Core\Field\FieldItemListInterface $field */
      $fieldConfig = $config->getField($bundleId, $field->getName());

      if (!$fieldConfig->enabled) {
        continue;
      }

      $rtfValue = $fieldConfig->rtf;

      if ($rtfValue && $rtfValue !== 'no') {
        $fields[] = [
          'entity_type' => $entity->getEntityTypeId(),
          'bundle' => $bundleId,
          'field' => $field,
          'mode' => $rtfValue,
        ];
      }

    }

    return $fields;
  }

  /**
   * Re-fetches the user bypassing the cache.
   *
   * @param string $user_id
   *   The ID of the user to fetch.
   *
   * @return \Drupal\user\UserInterface
   *   The user that was fetched.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function refetchUser($user_id) {
    return $this->entityTypeManager->getStorage('user')
      ->loadUnchanged($user_id);
  }

  /**
   * Checks that the export directory has been set.
   *
   * @return bool
   *   Indicates whether the export directory has been configured and exists.
   */
  private function checkExportDirectoryExists() {
    $directory = $this->configFactory->get(RemovalSettingsForm::CONFIG_KEY)
      ->get(RemovalSettingsForm::EXPORT_DIRECTORY);
    return !empty($directory) && \file_prepare_directory($directory);
  }

  /**
   * Stores the task log to the configured directory as JSON.
   *
   * @param \Drupal\gdpr_tasks\Entity\TaskInterface $task
   *   The task in progress.
   * @param array $log
   *   Log of processed fields.
   */
  private function writeLogToFile(TaskInterface $task, array $log) {
    $filename = 'GDPR_RTF_' . \date('Y-m-d H-i-s') . '_' . $task->uuid() . '.json';
    $dir = $this->configFactory->get(RemovalSettingsForm::CONFIG_KEY)
      ->get(RemovalSettingsForm::EXPORT_DIRECTORY);

    $filename = $dir . '/' . $filename;

    // Don't serialize the whole entity as we don't need all fields.
    $output = [
      'task_id' => $task->id(),
      'task_uuid' => $task->uuid(),
      'owner_id' => $task->getOwnerId(),
      'created' => $task->getCreatedTime(),
      'processed_by' => $this->currentUser->id(),
      'log' => $log,
    ];

    \file_put_contents($filename, \json_encode($output));
  }

}
