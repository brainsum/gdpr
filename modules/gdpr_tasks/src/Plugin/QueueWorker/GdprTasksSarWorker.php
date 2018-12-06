<?php

namespace Drupal\gdpr_tasks\Plugin\QueueWorker;

use Drupal\Core\File\FileSystem;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\gdpr_fields\EntityTraversalFactory;
use Drupal\gdpr_tasks\Entity\TaskInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes SARs tasks when data processing is required.
 *
 * This will firstly prepare and gather user data when the task is requested
 * and later compile the export files into a single zip archive for download.
 *
 * @QueueWorker(
 *   id = "gdpr_tasks_process_gdpr_sar",
 *   title = @Translation("Process SARs Tasks"),
 *   cron = {"time" = 60}
 * )
 */
class GdprTasksSarWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The message storage handler.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $taskStorage;

  /**
   * The uuid service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManager
   */
  protected $fieldTypePluginManager;

  /**
   * The gdpr sars task queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * The rta traversal service.
   *
   * @var \Drupal\gdpr_tasks\Traversal\RightToAccessDisplayTraversal
   */
  protected $rtaTraversal;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new MessageDeletionWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid service.
   * @param \Drupal\Core\Field\FieldTypePluginManager $field_type_plugin_manager
   *   The field type plugin manager.
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   The gdpr sars task queue.
   * @param \Drupal\gdpr_fields\EntityTraversalFactory $rta_traversal
   *   The rta traversal service.
   * @param \Drupal\Core\File\FileSystem $file_system
   *   The file system.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager, UuidInterface $uuid, FieldTypePluginManager $field_type_plugin_manager, QueueInterface $queue, EntityTraversalFactory $rta_traversal, FileSystem $file_system, MessengerInterface $messenger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->taskStorage = $entity_type_manager->getStorage('gdpr_task');
    $this->uuid = $uuid;
    $this->fieldTypePluginManager = $field_type_plugin_manager;
    $this->queue = $queue;
    $this->rtaTraversal = $rta_traversal;
    $this->fileSystem = $file_system;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('uuid'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('queue')->get('gdpr_tasks_process_gdpr_sar'),
      $container->get('gdpr_tasks.rta_traversal'),
      $container->get('file_system'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!empty($data)) {
      /* @var \Drupal\gdpr_tasks\Entity\TaskInterface $task */
      $task = $this->taskStorage->load($data);

      // Work out where we are up to and what to do next.
      switch ($task->getStatus()) {
        // Received but not initialised.
        case 'requested':
          // @todo Make immediate building configurable for performance.
          $this->initialise($task, TRUE);
          break;

        // Initialised but not built.
        case 'building':
          $this->build($task);
          break;

        // Processed by staff and ready to compile.
        case 'processed':
          $this->compile($task);
          break;
      }
    }
  }

  /**
   * Initialise our request.
   *
   * @param \Drupal\gdpr_tasks\Entity\TaskInterface $task
   *   The task.
   * @param bool $build_now
   *   Whether to build the entity data immediate or defer to cron.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function initialise(TaskInterface $task, $build_now = FALSE) {
    /* @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $field */
    $field = $task->get('sar_export');
    /* @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $field->getFieldDefinition();
    $settings = $field_definition->getSettings();

    $config = [
      'field_definition' => $field_definition,
      'name' => $field->getName(),
      'parent' => $field->getParent(),
    ];
    /* @var \Drupal\file\Plugin\Field\FieldType\FileItem $field_type */
    $field_type = $this->fieldTypePluginManager->createInstance($field_definition->getType(), $config);

    // Prepare destination.
    $directory = $field_type->getUploadLocation();
    if (!\file_prepare_directory($directory, FILE_CREATE_DIRECTORY)) {
      throw new \RuntimeException('GDPR SARs upload directory is not writable.');
    }

    // Get a suitable namespace for gathering our files.
    do {
      // Generate a UUID.
      $uuid = $this->uuid->generate();

      // Check neither the file exists nor the directory.
      if (file_exists("{$directory}/{$uuid}.zip") || file_exists("{$directory}/{$uuid}/")) {
        continue;
      }

      // Generate the zip file to reserve our namespace.
      $file = _gdpr_tasks_file_save_data('', $task->getOwner(), "{$directory}/{$uuid}.zip", FILE_EXISTS_ERROR);
    } while (!$file);

    // Prepare the directory for our sub-files.
    $content_directory = "{$directory}/{$uuid}";
    file_prepare_directory($content_directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

    // Store the file against the task.
    $values = [
      'target_id' => $file->id(),
      'display' => (int) $settings['display_default'],
      'description' => '',
    ];

    $task->sar_export = $values;
    $task->status = 'building';
    $task->save();

    // Start the build process.
    if ($build_now) {
      $this->build($task);
    }
    else {
      // Queue for building.
      $this->queue->createQueue();
      $this->queue->createItem($task->id());
    }

  }

  /**
   * Build the export files.
   *
   * @param \Drupal\gdpr_tasks\Entity\TaskInterface $task
   *   The task.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function build(TaskInterface $task) {
    /* @var \Drupal\file\Plugin\Field\FieldType\FileFieldItemList $field */
    $field = $task->get('sar_export');
    /* @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $field->getFieldDefinition();
    $settings = $field_definition->getSettings();

    $config = [
      'field_definition' => $field_definition,
      'name' => $field->getName(),
      'parent' => $field->getParent(),
    ];
    /* @var \Drupal\file\Plugin\Field\FieldType\FileItem $field_type */
    $field_type = $this->fieldTypePluginManager->createInstance($field_definition->getType(), $config);

    // Prepare destination.
    $directory = $field_type->getUploadLocation();
    $directory .= '/' . basename($field->entity->uri->value, '.zip');

    // Gather our entities.
    // @todo: Move this inline.
    $rtaTraversal = $this->rtaTraversal->getTraversal($task->getOwner());
    $rtaTraversal->traverse();
    $all_data = $rtaTraversal->getResults();

    // Build our export files.
    $csvs = [];
    foreach ($all_data as $plugin_id => $data) {
      if ($plugin_id == '_assets') {
        $task->sar_export_assets = $data;
        continue;
      }

      // Build the headers if required.
      if (!isset($csvs[$data['file']]['_header'][$data['plugin_name']])) {
        $csvs[$data['file']]['_header'][$data['plugin_name']] = $data['label'];
      }

      // Initialise and fill out the row to make sure things come in a
      // consistent order.
      if (!isset($csvs[$data['file']][$data['row_id']])) {
        $csvs[$data['file']][$data['row_id']] = [];
      }
      $csvs[$data['file']][$data['row_id']] += array_fill_keys(array_keys($csvs[$data['file']]['_header']), '');

      // Put our piece of information in place.
      $csvs[$data['file']][$data['row_id']][$data['plugin_name']] = $data['value'];
    }

    // Gather existing files.
    $files = [];
    if (!empty($task->sar_export_parts)) {
      foreach ($task->sar_export_parts as $item) {
        $filename = basename($item->entity->uri->value, '.csv');
        $files[$filename] = $item->entity;
      }
    }

    // Write our CSV files.
    foreach ($csvs as $filename => $data) {
      if (!isset($files[$filename])) {
        // Create an empty file.
        $file = _gdpr_tasks_file_save_data('', $task->getOwner(), "{$directory}/{$filename}.csv", FILE_EXISTS_REPLACE);

        $values = [
          'target_id' => $file->id(),
          'display' => (int) $settings['display_default'],
          'description' => '',
        ];

        // Track the file.
        $task->sar_export_parts[] = $values;
      }
      else {
        $file = $files[$filename];
      }

      $this->writeCsv($file->uri->value, $data);
      $file->save();
    }

    // Update the status.
    $task->status = 'reviewing';
    $task->save();
  }

  /**
   * Compile the SAR into a downloadable zip.
   *
   * @param \Drupal\gdpr_tasks\Entity\TaskInterface $task
   *   The task.
   */
  public function compile(TaskInterface $task) {
    // Compile all files into a single zip.
    /* @var \Drupal\file\Entity\File $file */
    $file = $task->sar_export->entity;
    if (NULL === $file) {
      $this->messenger->addError(t('SARs Export File not found for task @task_id.', ['@task_id' => $task->id()]));
      return;
    }

    $file_path = $this->fileSystem->realpath($file->uri->value);

    $zip = new \ZipArchive();
    if (!$zip->open($file_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
      // @todo: Improve error handling.
      $this->messenger->addError(t('Error opening file.'));
      return;
    }

    // Gather all the files we need to include in this package.
    $part_files = [];
    foreach ($task->sar_export_parts as $item) {
      /* @var \Drupal\file\Entity\File $part_file */
      $part_file = $item->entity;
      $part_files[] = $part_file;

      // Add the file to the zip.
      // @todo: Add error handling.
      $zip->addFile($this->fileSystem->realpath($part_file->uri->value), $part_file->filename->value);
    }

    // Add in any attached files that need including.
    foreach ($task->sar_export_assets as $item) {
      $asset_file = $item->entity;

      // Add the file to the zip.
      $filename = "assets/{$asset_file->fid->value}." . pathinfo($asset_file->uri->value, PATHINFO_EXTENSION);
      // @todo: Add error handling.
      $zip->addFile($this->fileSystem->realpath($asset_file->uri->value), $filename);
    }

    // Clear our parts and assets file lists.
    $task->sar_export_parts = NULL;
    $task->sar_export_assets = NULL;

    // Close the zip to write it to disk.
    // @todo: Add error handling.
    $zip->close();

    // Save the file to update the file size.
    $file->save();

    // Remove the partial files.
    foreach ($part_files as $part_file) {
      $part_file->delete();
    }

    // @todo Clean up the parts directory.
    // Update the status as completed.
    $task->status = 'closed';
    $task->save();
  }

  /**
   * Read data from a CSV file.
   *
   * @param string $filename
   *   The filename to read from (supports streams).
   *
   * @return array
   *   CSV file data.
   *
   * @todo: Use something like this instead:
   *        \Consolidation\OutputFormatters\Formatters\CsvFormatter
   */
  public static function readCsv($filename) {
    $data = [];
    $handle = fopen($filename, 'r');
    while (!feof($handle)) {
      $data[] = fgetcsv($handle);
    }
    fclose($handle);
    return $data;
  }

  /**
   * Write data to a CSV file.
   *
   * @param string $filename
   *   The filename to write to (supports streams).
   * @param array $content
   *   The data to write, an array containing each row as an array.
   *
   * @todo: Use something like this instead:
   *        \Consolidation\OutputFormatters\Formatters\CsvFormatter
   */
  protected function writeCsv($filename, array $content) {
    $handler = fopen($filename, 'w');
    // Write the UTF-8 BOM header so excel handles the encoding.
    fprintf($handler, chr(0xEF) . chr(0xBB) . chr(0xBF));
    foreach ($content as $row) {
      fputcsv($handler, $row);
    }
    fclose($handler);
  }

}
