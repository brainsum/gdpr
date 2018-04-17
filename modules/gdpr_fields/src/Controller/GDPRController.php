<?php

namespace Drupal\gdpr_fields\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\gdpr_fields\GDPRCollector;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * @return array
   *   The Views plugins report page.
   */
  public function fieldsList($mode) {
    $output = [];
    $entities = [];
    $include_not_configured = $mode == 'all';
    $this->collector->getEntities($entities);

    $output['filter'] = $this->formBuilder()->getForm('Drupal\gdpr_fields\Form\GdprFieldFilterForm');
    $output['#attached']['library'][] = 'gdpr_fields/field-list';


    foreach ($entities as $entity_type => $bundles) {
      $output[$entity_type] = [
        '#type' => 'details',
        '#title' => t($entity_type),
        '#open' => TRUE,
      ];

      if (count($bundles) > 1) {
        foreach ($bundles as $bundle_id) {
          $output[$entity_type][$bundle_id] = [
            '#type' => 'details',
            '#title' => t($bundle_id),
            '#open' => TRUE,
          ];
          $output[$entity_type][$bundle_id]['fields'] = $this->buildFieldTable($entity_type, $bundle_id, $include_not_configured);
        }
      }
      else {
        // Don't add another collapsible wrapper around single bundle entities.
        $bundle_id = reset($bundles);
        $output[$entity_type][$bundle_id]['fields'] = $this->buildFieldTable($entity_type, $bundle_id, $include_not_configured);
      }
    }

    return $output;
  }

  /**
   * Build a table for entity field list.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param string $bundle_id
   *   The entity bundle id.
   *
   * @return array
   *   Renderable array for field list table.
   */
  protected function buildFieldTable($entity_type, $bundle_id, $include_not_configured) {
    $rows = $this->collector->listFields($entity_type, $bundle_id, $include_not_configured);
    // Sort rows by field name.
    ksort($rows);

    $table = [
      '#type' => 'table',
      '#header' => [t('Name'), t('Type'), t('Right to access'), t('Right to be forgotten'), t('Notes'), ''],
    //  '#rows' => $rows,
      '#sticky' => TRUE,
      '#empty' => t('There are no GDPR fields for this entity.'),
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
   */
  public function rtaData(UserInterface $user) {
    $rows = [];
    $entities = [];
    $this->collector->getValueEntities($entities, 'user', $user);

    foreach ($entities as $entity_type => $bundles) {
      foreach ($bundles as $bundle_entity) {
        $rows += $this->collector->fieldValues($entity_type, $bundle_entity, ['rta' => 'rta']);
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
   */
  public function rtfData(UserInterface $user) {
    $rows = [];
    $entities = [];
    $this->collector->getValueEntities($entities, 'user', $user);

    foreach ($entities as $entity_type => $bundles) {
      foreach ($bundles as $bundle_entity) {
        $rows += $this->collector->fieldValues($entity_type, $bundle_entity, ['rtf' => 'rtf']);
      }
    }

    // Sort rows by field name.
    ksort($rows);
    return $rows;
  }

}
