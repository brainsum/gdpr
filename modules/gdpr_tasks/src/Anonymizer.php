<?php

namespace Drupal\gdpr_tasks;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\gdpr_fields\EntityTraversalFactory;
use Drupal\gdpr_tasks\Entity\TaskInterface;
use Drupal\gdpr_tasks\Form\RemovalSettingsForm;

/**
 * Anonymizes or removes field values for GDPR.
 */
class Anonymizer {

  use StringTranslationTrait;

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
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private $currentUser;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $configFactory;

  /**
   * Traverses the entity hierarchy finding GDPR fields.
   *
   * @var \Drupal\gdpr_tasks\Traversal\RightToBeForgottenEntityTraversal
   */
  private $traversalFactory;

  /**
   * Anonymizer constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\gdpr_fields\EntityTraversalFactory $traversalFactory
   *   Instantiates a traverser class.
   */
  public function __construct(
    Connection $database,
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $configFactory,
    EntityTraversalFactory $traversalFactory
  ) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $configFactory;
    $this->traversalFactory = $traversalFactory;
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

    if (!$this->checkExportDirectoryExists()) {
      $errors[] = $this->t('An export directory has not been set. Please set this %link.', [
        '%link' => Link::fromTextAndUrl('here', Url::fromRoute('gdpr_tasks.remove_settings'))->toString(),
      ]);
      return $errors;
    }

    // Traverser does the actual anonymizing.
    $traverser = $this->traversalFactory->getTraversal($user);
    $result = $traverser->getResults();

    $log = $result['log'];
    $errors = $result['errors'];
    $successes = $result['successes'];
    $failures = $result['failures'];
    $deletions = $result['to_delete'];

    $task->get('removal_log')->setValue($log);

    if (count($failures) === 0) {
      $transaction = $this->database->startTransaction();

      try {
        /* @var \Drupal\Core\Entity\EntityInterface $entity */
        foreach ($successes as $entity) {
          $entity->save();
        }

        foreach ($deletions as $entity) {
          $entity->delete();
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
