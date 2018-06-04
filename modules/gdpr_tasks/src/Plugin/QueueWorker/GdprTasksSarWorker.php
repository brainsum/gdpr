<?php


/**
 * Queue worker callback for processing SARs requests.
 */
class GdprTasksSarWorker {

  /**
   * Process the SARs request.
   */
  public function processItem($data) {
    /* @var \GDPRTask $task */
    $task = gdpr_task_load($data);

    // Work out where we are up to and what to do next.
    switch ($task->status) {
      // Received but not initialised.
      case 'requested':
        $this->initialise($task);
        break;

      // Processed by staff and ready to compile.
      case 'processed':
        $this->compile($task);
        break;
    }
  }

  /**
   * Initialise our request.
   *
   * @param \GDPRTask $task
   *   The task.
   */
  protected function initialise(\GDPRTask $task) {
    $field_info = field_info_field('gdpr_tasks_sar_export');
    $directory = $field_info['settings']['uri_scheme'] . '://gdpr_sars';
    file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

    // Get a suitable namespace for gathering our files.
    do {
      // Generate a UUID.
      $uuid = ctools_uuid_generate();

      // Check neither the file exists nor the directory.
      if (file_exists("{$directory}/{$uuid}.zip") || file_exists("{$directory}/{$uuid}/")) {
        continue;
      }

      // Generate the zip file to reserve our namespace.
      $file = file_save_data('', "{$directory}/{$uuid}.zip", FILE_EXISTS_ERROR);
    } while (!$file);

    // Prepare the directory for our sub-files.
    $content_directory = "{$directory}/{$uuid}";
    file_prepare_directory($content_directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

    // Store the file against the task.
    $task->gdpr_tasks_sar_export[LANGUAGE_NONE][0] = array(
      'fid' => $file->fid,
      'display' => TRUE,
    );
    $task->status = 'building';
    $task->save();

    // Start the build process.
    $this->build($task);
  }

  /**
   * Build the export files.
   *
   * @param \GDPRTask $task
   *   The task.
   */
  protected function build(\GDPRTask $task) {
    $wrapper = $task->wrapper();

    $field_info = field_info_field('gdpr_tasks_sar_export');
    $directory = $field_info['settings']['uri_scheme'] . '://gdpr_sars/';
    $directory .= basename($task->wrapper()->gdpr_tasks_sar_export->file->url->value(), '.zip');

    // Gather our entities.
    // @todo: Move this inline.
    $all_data = gdpr_tasks_collect_rta_data($task->getOwner());

    // Build our export files.
    $csvs = array();
    foreach ($all_data as $plugin_id =>  $data) {
      if ($plugin_id == '_assets') {
        $wrapper->gdpr_tasks_sar_export_assets = $data;
        continue;
      }

      // Build the headers if required.
      if (!isset($csvs[$data['file']]['_header'][$data['plugin_name']])) {
        $csvs[$data['file']]['_plugin_id'][$data['plugin_name']] = $data['plugin_name'];
        $csvs[$data['file']]['_header'][$data['plugin_name']] = $data['label'];
      }

      // Initialise and fill out the row to make sure things come in a
      // consistent order.
      if (!isset($csvs[$data['file']][$data['row_id']])) {
        $csvs[$data['file']][$data['row_id']] = array();
      }
      $csvs[$data['file']][$data['row_id']] += array_fill_keys(array_keys($csvs[$data['file']]['_header']), '');

      // Put our piece of information in place.
      $csvs[$data['file']][$data['row_id']][$data['plugin_name']] = $data['value'];
    }

    // Gather existing files.
    $files = array();
    foreach ($wrapper->gdpr_tasks_sar_export_parts as $item) {
      $filename = basename($item->file->url->value(), '.csv');
      $files[$filename] = $item->file->value();
    }

    // Write our CSV files.
    foreach ($csvs as $filename => $data) {
      if (!isset($files[$filename])) {
        // Create an empty file.
        $file = file_save_data('', "{$directory}/{$filename}.csv", FILE_EXISTS_REPLACE);

        // Track the file.
        $task->gdpr_tasks_sar_export_parts[LANGUAGE_NONE][] = array(
          'fid' => $file->fid,
          'display' => TRUE,
        );
      }
      else {
        $file = $files[$filename];
      }

      $this->writeCsv($file->uri, $data);
      file_save($file);
    }

    // Update the status.
    $task->status = 'reviewing';
    $task->save();
  }

  /**
   * Compile the SAR into a downloadable zip.
   *
   * @param \GDPRTask $task
   *   The task.
   */
  protected function compile(\GDPRTask $task) {
    // Compile all files into a single zip.
    $wrapper = $task->wrapper();
    $file = $wrapper->gdpr_tasks_sar_export->file;
    $file_path = drupal_realpath($file->value()->uri);

    $zip = new ZipArchive();
    if (!$zip->open($file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
      // @todo: Improve error handling.
      drupal_set_message('error opening file', 'error');
      return;
    }

    // Gather all the files we need to include in this package.
    $part_files = array();
    foreach ($wrapper->gdpr_tasks_sar_export_parts as $item) {
      $part_file = $item->file->value();
      $part_files[] = $part_file;

      // Re-write the file to remove the header.
      $data = $this->readCsv($part_file->uri);
      array_shift($data);
      $this->writeCsv($part_file->uri, $data);
      file_save($part_file);

      // Add the file to the zip.
      // @todo: Add error handling.
      $zip->addFile(drupal_realpath($part_file->uri), $part_file->filename);
    }

    // Add in any attached files that need including.
    foreach ($wrapper->gdpr_tasks_sar_export_assets as $item) {
      $asset_file = $item->file->value();

      // Add the file to the zip.
      $filename = "assets/{$asset_file->fid}." . pathinfo($asset_file->uri, PATHINFO_EXTENSION);
      // @todo: Add error handling.
      $zip->addFile(drupal_realpath($asset_file->uri), $filename);
    }

    // Clear our parts and assets file lists.
    $task->gdpr_tasks_sar_export_parts = NULL;
    $task->gdpr_tasks_sar_export_assets = NULL;

    // Close the zip to write it to disk.
    // @todo: Add error handling.
    $zip->close();

    // Save the file to update the file size.
    $file->save();

    // Remove the partial files.
    foreach ($part_files as $part_file) {
      file_delete($part_file);
    }

    // Clean up the parts directory.
    // @todo.

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
   */
  public static function readCsv($filename) {
    $data = array();
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
