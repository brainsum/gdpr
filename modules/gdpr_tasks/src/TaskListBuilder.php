<?php

namespace Drupal\gdpr_tasks;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of Task entities.
 *
 * @ingroup gdpr_tasks
 */
class TaskListBuilder extends EntityListBuilder {

  /**
   * The entity bundle storage class.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $bundleStorage;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager')->getStorage($entity_type->getBundleEntityType())
    );
  }

  /**
   * Constructs a new EntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\EntityStorageInterface $bundle_storage
   *   The entity bundle storage class.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityStorageInterface $bundle_storage) {
    parent::__construct($entity_type, $storage);
    $this->bundleStorage = $bundle_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Task ID');
    $header['name'] = $this->t('Name');
    $header['user'] = $this->t('User');
    $header['type'] = $this->t('Type');
    $header['created'] = $this->t('Requested');
    $header['requested_by'] = $this->t('Requested by');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\gdpr_tasks\Entity\Task */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.gdpr_task.canonical',
      ['gdpr_task' => $entity->id()]
    );
    $row['user'] = $entity->getOwner()->toLink()->toString();
    $row['type'] = $this->bundleStorage->load($entity->bundle())->label();
    $row['created'] = DateTimePlus::createFromTimestamp($entity->getCreatedTime())->format('j/m/Y - H:m');

    $date_formatter = \Drupal::service('date.formatter');
    $row['created'] .= ' - ' . $date_formatter->formatDiff(
      $entity->getCreatedTime(),
      \Drupal::time()->getRequestTime(), ['granularity' => 1]) . ' ago';

    $row['requested_by'] = $entity->requested_by->entity->toLink()->toString();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    if ($entity->access('view') && $entity->hasLinkTemplate('canonical')) {
      $operations['view'] = [
        'title' => $this->t('View'),
        'weight' => 0,
        'url' => $this->ensureDestination($entity->toUrl()),
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['requested']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => 'Requested tasks',
    ];

    $build['requested']['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => [],
      '#empty' => $this->t('There is no open @label yet.', ['@label' => $this->entityType->getLabel()]),
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ];

    $build['reviewing']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => 'In-review tasks',
    ];

    $build['reviewing']['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => [],
      '#empty' => $this->t('There are no tasks to be reviewed yet.'),
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ];

    $build['processed']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => 'Processed tasks',
    ];

    $build['processed']['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => [],
      '#empty' => $this->t('There are no processed tasks yet.'),
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ];

    $build['closed']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h3',
      '#value' => 'Closed tasks',
    ];

    $build['closed']['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#rows' => [],
      '#empty' => $this->t('There are no closed tasks yet.'),
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ];
    /* @var $entity \Drupal\gdpr_tasks\Entity\Task */
    foreach ($this->load() as $entity) {
      if ($row = $this->buildRow($entity)) {
        $build[$entity->status->value]['table']['#rows'][$entity->id()] = $row;
      }
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $build['pager'] = [
        '#type' => 'pager',
      ];
    }

    return $build;
  }

}
