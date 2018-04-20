<?php

namespace Drupal\gdpr_view_export_log\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\gdpr_view_export_log\Entity\ExportAudit;
use Drupal\user\Entity\User;


/**
 * Class ExportAuditController.
 *
 * @package Drupal\gdpr_view_export_log\Controller
 */
class ExportAuditController extends ControllerBase {

  /**
   * Views the users that were part of an export audit.
   *
   * @param string $id
   *   The audit id.
   *
   * @return array
   *   Render array
   */
  public function viewUsers($id) {
    $query = \Drupal::database()->select('gdpr_view_export_audit_user_ids', 'g');

    $count_query = clone $query;
    $count_query->addExpression('count(g.id)');

    $paged_query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender');
    $paged_query->limit(50);
    $paged_query->setCountQuery($count_query);
    $paged_query->addJoin('left', 'users_field_data', 'u', 'g.user_id = u.uid');

    $results = $paged_query->fields('g', ['user_id'])
      ->fields('u', ['name'])
      ->condition('g.log_id', $id)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);


    $output = [
      'back' => [
        '#type' => 'link',
        '#url' => Url::fromRoute('entity.gdpr_view_export_audit.collection'),
        '#title' => $this->t('Back to export list'),
      ],

      'table' => [
        '#type' => 'table',
        '#header' => ['ID', 'Username', ''],
        '#empty' => 'Could not locate any users in this export.',
      ],

      'pager' => [
        '#theme' => 'pager',
        '#weight' => 5,
        '#element' => 0,
        '#parameters' => [],
        '#quantity' => 9,
        '#route_name' => '<none>',
        '#tags' => '',
      ],
    ];


    foreach ($results as $result) {
      $output['table'][$result['user_id']]['userid'] = [
        '#markup' => $result['user_id'],
      ];

      $output['table'][$result['user_id']]['username'] = [
        '#type' => 'link',
        '#url' => Url::fromRoute('entity.user.canonical', ['user' => $result['user_id']]),
        '#title' => $result['name'],
      ];


      $output['table'][$result['user_id']]['operations'] = [
        '#type' => 'operations',
        '#links' => [
          'remove' => [
            'title' => $this->t('Remove'),
            'url' => Url::fromRoute('gdpr_view_export_log.delete_user', [
              'id' => $id,
              'user_id' => $result['user_id'],
            ]),
          ],
        ],
      ];
    }

    return $output;
  }

}
