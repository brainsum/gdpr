<?php

namespace Drupal\gdpr_dump\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gdpr_dump\Sanitizer\GdprSanitizerPluginManager;
use Drupal\gdpr_dump\Service\GdprDatabaseManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SettingsForm.
 *
 * @package Drupal\gdpr_dump\Form
 */
class SettingsForm extends ConfigFormBase {

  const GDPR_DUMP_CONF_KEY = 'gdpr_dump.table_map';

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Database manager.
   *
   * @var \Drupal\gdpr_dump\Service\GdprDatabaseManager
   */
  protected $databaseManager;

  /**
   * The plugin manager for GDPR Sanitizers.
   *
   * @var \Drupal\gdpr_dump\Sanitizer\GdprSanitizerPluginManager
   */
  protected $pluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('database'),
      $container->get('plugin.manager.gdpr_sanitizer'),
      $container->get('gdpr_dump.database_manager')
    );
  }

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\gdpr_dump\Sanitizer\GdprSanitizerPluginManager $pluginManager
   *   The plugin manager for GDPR Sanitizers.
   * @param \Drupal\gdpr_dump\Service\GdprDatabaseManager $gdprDatabaseManager
   *   Database manager service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    Connection $database,
    GdprSanitizerPluginManager $pluginManager,
    GdprDatabaseManager $gdprDatabaseManager
  ) {
    parent::__construct($configFactory);
    $this->database = $database;
    $this->pluginManager = $pluginManager;
    $this->databaseManager = $gdprDatabaseManager;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::GDPR_DUMP_CONF_KEY,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gdpr_dump_settings_form';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Database\InvalidQueryException
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $form['description'] = [
      '#markup' => $this->t('Check the checkboxes for each table columns containing sensitive data!'),
    ];

    $form['tables'] = [
      '#type' => 'container',
    ];

    /** @var \Drupal\gdpr_dump\Sanitizer\GdprSanitizerPluginManager $pluginManager */
    $plugins = [];
    foreach ($this->pluginManager->getDefinitions() as $definition) {
      $plugins[$definition['id']] = $definition['label'];
    }

    /* @todo:
     * Maybe divide by type (int, varchar, etc) and
     * display only appropriate ones for the actual selects.
     */
    /* @todo
     * UX
     */
    $sanitationOptions = [
      '#type' => 'select',
      '#title' => $this->t('Sanitation plugin'),
      '#options' => $plugins,
      '#empty_value' => 'none',
      '#empty_option' => $this->t('- None -'),
    ];

    $config = $this->config(self::GDPR_DUMP_CONF_KEY)->get('mapping');
    /** @var array $columns */
    foreach ($this->databaseManager->getTableColumns() as $table => $columns) {
      $rows = [];
      foreach ($columns as $column) {
        $currentOptions = $sanitationOptions;
        $checked = 0;
        if (isset($config[$table][$column['COLUMN_NAME']])) {
          $checked = 1;
          $currentOptions['#default_value'] = $config[$table][$column['COLUMN_NAME']];
        }

        $rows[] = [
          '#type' => 'container',
          'name' => [
            '#type' => 'value',
            '#value' => $column['COLUMN_NAME'],
          ],
          'sanitize' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Sanitize <strong>@field_name</strong>?', [
              '@field_name' => $column['COLUMN_NAME'],
            ]),
            '#default_value' => $checked,
          ],
          'type' => [
            '#type' => 'item',
            '#markup' => $column['DATA_TYPE'],
            '#title' => $this->t('Field type'),
          ],
          'comment' => [
            '#type' => 'item',
            '#markup' => empty($column['COLUMN_COMMENT']) ? '-' : $column['COLUMN_COMMENT'],
            '#title' => $this->t('Field comment'),
          ],
          'option' => $currentOptions,
        ];
      }

      $form['tables'][$table] = [
        '#type' => 'details',
        '#title' => $table,
        'columns' => [
          '#type' => 'container',
          'data' => $rows,
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Config\ConfigValueException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->hasValue('tables')) {
      $config = [];
      /** @var array $tables */
      $tables = $form_state->getValue('tables', []);
      foreach ($tables as $table => $row) {
        foreach ($row['columns']['data'] as $data) {
          if ($data['sanitize'] === 1) {
            $config[$table][$data['name']] = $data['option'];
          }
        }
      }

      $this->configFactory->getEditable(self::GDPR_DUMP_CONF_KEY)
        ->set('mapping', $config)
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

}
