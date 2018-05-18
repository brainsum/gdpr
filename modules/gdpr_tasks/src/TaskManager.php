<?php

namespace Drupal\gdpr_tasks;

use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Session\AccountProxy;

/**
 * Defines a helper class for stuff related to views data.
 */
class TaskManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $taskStorage;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Constructs a TaskManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(EntityTypeManager $entity_type_manager, AccountProxy $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->taskStorage = $entity_type_manager->getStorage('gdpr_task');
    $this->currentUser = $current_user;
  }

  /**
   * Fetch tasks for a certain user.
   *
   * @param null|\Drupal\Core\Session\AccountInterface $account
   *   The user account to get tasks for. Defaults to current user.
   * @param null|string $type
   *   Optionally filter by task type.
   *
   * @return array|\Drupal\gdpr_tasks\Entity\TaskInterface[]
   *   Array of fully loaded task entities.
   */
  public function getUserTasks($account = NULL, $type = NULL) {
    $tasks = [];

    if (!$account) {
      $account = $this->currentUser->getAccount();
    }

    $query = $this->taskStorage->getQuery();
    $query->condition('user_id', $account->id(), '=');

    if ($type) {
      $query->condition('type', $type, '=');
    }

    if (!empty($ids = $query->execute())) {
      $tasks = $this->taskStorage->loadMultiple($ids);
    }

    return $tasks;
  }

  /**
   * Writes array data to a csv file.
   *
   * @param array $data
   *   The data to be stored in csv.
   * @param string $dirname
   *   The local path or stream wrapper for destination directory.
   *
   * @return string
   *   The uri path of the created file.
   */
  public function toCsv(array $data, $dirname = 'private://') {
    // Prepare destination.
    file_prepare_directory($dirname, FILE_CREATE_DIRECTORY);

    // Generate a file entity.
    $random = new Random();
    $destination = $dirname . '/' . $random->name(10, TRUE) . '.csv';

    // Update csv with actual data.
    $fp = fopen($destination, 'w');
    foreach ($data as $line) {
      fputcsv($fp, $line);
    }
    fclose($fp);

    return $destination;
  }

}
