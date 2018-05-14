<?php

namespace Drupal\gdpr_fields\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\gdpr_fields\GDPRCollector;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\gdpr_fields\Form\GdprFieldFilterForm;

/**
 * Returns responses for GDPR Field routes.
 */
class GDPRController extends ControllerBase {

  /**
   * Stores the Views data cache object.
   *
   * @var \Drupal\gdpr_fields\GDPRCollector
   */
  protected $collector;

  /**
   * Constructs a new GDPRController.
   *
   * @param \Drupal\gdpr_fields\GDPRCollector $collector
   *   The GDPR collector service.
   */
  public function __construct(GDPRCollector $collector) {
    $this->collector = $collector;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gdpr_fields.collector')
    );
  }

  /**
   * Lists all fields with GDPR sensitivity.
   *
   * @param string $mode
   *   The list mode.
   *
   * @return array
   *   The Views plugins report page.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function fieldsList($mode) {
    $output = [];
    $entities = [];
    $includeNotConfigured = ($mode === 'all');
    $this->collector->getEntities($entities);

    $output['filter'] = $this->formBuilder()->getForm(GdprFieldFilterForm::class);
    $output['#attached']['library'][] = 'gdpr_fields/field-list';

    foreach ($entities as $entityType => $bundles) {
      $output[$entityType] = [
        '#type' => 'details',
        '#title' => $entityType,
        '#open' => TRUE,
      ];

      if (\count($bundles) > 1) {
        foreach ($bundles as $bundle_id) {
          $output[$entityType][$bundle_id] = [
            '#type' => 'details',
            '#title' => $bundle_id,
            '#open' => TRUE,
          ];
          $output[$entityType][$bundle_id]['fields'] = $this->buildFieldTable($entityType, $bundle_id, $includeNotConfigured);
        }
      }
      else {
        // Don't add another collapsible wrapper around single bundle entities.
        $bundle_id = \reset($bundles);
        $output[$entityType][$bundle_id]['fields'] = $this->buildFieldTable($entityType, $bundle_id, $includeNotConfigured);
      }
    }

    return $output;
  }

  /**
   * Build a table for entity field list.
   *
   * @param string $entityType
   *   The entity type id.
   * @param string $bundle_id
   *   The entity bundle id.
   * @param bool $includeNotConfigured
   *   Include fields for entities that have not yet been configured.
   *
   * @return array
   *   Renderable array for field list table.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildFieldTable($entityType, $bundle_id, $includeNotConfigured = FALSE) {
    $rows = $this->collector->listFields($bundle_id, $entityType, $includeNotConfigured);
    // Sort rows by field name.
    ksort($rows);

    $table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Type'),
        $this->t('Right to access'),
        $this->t('Right to be forgotten'),
        $this->t('Notes'),
        '',
      ],
      '#sticky' => TRUE,
      '#empty' => $this->t('There are no GDPR fields for this entity.'),
    ];

    $i = 0;
    foreach ($rows as $row) {
      $table[$i]['title'] = [
        '#plain_text' => $row['title'],
      ];

      $table[$i]['type'] = [
        '#plain_text' => $row['type'],
      ];

      $table[$i]['gdpr_rta'] = [
        '#plain_text' => $row['gdpr_rta'],
      ];

      $table[$i]['gdpr_rtf'] = [
        '#plain_text' => $row['gdpr_rtf'],
      ];

      $table[$i]['notes'] = [
        '#markup' => empty($row['notes']) ? '' : '<span class="notes" data-icon="?"></span><div>' . $row['notes'] . '</div>',
      ];

      $table[$i]['edit'] = [
        '#markup' => !empty($row['edit']) ? $row['edit']->toString() : '',
      ];

      $i++;
    }

    return $table;
  }

  /**
   * Builds data for Right to Access data requests.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to fetch data for.
   *
   * @return array
   *   Structured array of user related data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function rtaData(UserInterface $user) {
    $rows = [];
    $entities = [];
    $this->collector->getValueEntities($entities, 'user', $user);

    foreach ($entities as $entityType => $bundles) {
      foreach ($bundles as $bundle_entity) {
        $rows += $this->collector->fieldValues($bundle_entity, $entityType, ['rta' => 'rta']);
      }
    }

    // Sort rows by field name.
    ksort($rows);
    return $rows;
  }

  /**
   * Builds data for Right to be Forgotten data requests.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to fetch data for.
   *
   * @return array
   *   Structured array of user related data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function rtfData(UserInterface $user) {
    $rows = [];
    $entities = [];
    $this->collector->getValueEntities($entities, 'user', $user);

    foreach ($entities as $entityType => $bundles) {
      foreach ($bundles as $bundle_entity) {
        $rows += $this->collector->fieldValues($bundle_entity, $entityType, ['rtf' => 'rtf']);
      }
    }

    // Sort rows by field name.
    ksort($rows);
    return $rows;
  }

}
