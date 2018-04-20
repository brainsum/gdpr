<?php

namespace Drupal\gdpr_view_export_log\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;

/**
 * Defines the list builder for the Export Audit log entity.
 *
 * @package Drupal\gdpr_view_export_log\Entity
 */
class ExportAuditListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return [
        'filename' => 'File Name',
        'location' => 'Location',
        'reason' => 'Reason',
        'created' => 'Date Exported',
        'expires' => 'Expires In',
        'owner' => 'Exported By',
      ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    // If a user ID was passed in the route then only load the exports
    // that contain the specified user ID.
    $user_id = \Drupal::routeMatch()->getParameter('user_id');
    if ($user_id) {
      $query = $this->getStorage()->getQuery()
        ->condition('user_ids', $user_id)
        ->sort($this->entityType->getKey('id'));

      // Only add the pager if a limit is specified.
      if ($this->limit) {
        $query->pager($this->limit);
      }
      return $query->execute();


    }
    else {
      return parent::getEntityIds();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $expires = $entity->get('length')->value;

    $date_exported = new \DateTimeImmutable();
    $date_exported = $date_exported->setTimestamp($entity->get('created')->value);
    $date_expires = $date_exported->modify("+{$expires} day");

    $now = new \DateTimeImmutable('now');
    $diff = $now->diff($date_expires);

    $row = [
      'filename' => $entity->get('filename')->value,
      'location' => $entity->get('location')->value,
      'reason' => $entity->get('reason')->value,
      'created' => date('Y-m-d H:i:s', $entity->get('created')->value),
    ];

    $row['expires']['data'] = [
      '#markup' => $diff->invert ? "<strong>{$diff->format('%r%a days')}</strong>" : $diff->format('%r%a days'),
    ];

    $row['user']['data'] = [
      '#theme' => 'username',
      '#account' => $entity->get('owner')->entity,
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOperations(EntityInterface $entity) {
    $ops = parent::buildOperations($entity);
    $ops['#links']['view_users'] = [
      'title' => $this->t('View Users'),
      'url' => Url::fromRoute('gdpr_view_export_log.view_users', ['id' => $entity->id()]),
      'weight' => 1,
    ];
    return $ops;
  }

}
