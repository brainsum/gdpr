<?php

namespace Drupal\gdpr_consent\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\gdpr_consent\Entity\ConsentAgreement;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConsentAgreementController.
 *
 *  Returns responses for Consent Agreement routes.
 */
class ConsentAgreementController extends ControllerBase {

  /**
   * The entity field manager for metadata.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  private $dateFormatter;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  private $renderer;

  /**
   * Constructs a ConsentAgreementController controller object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager for metadata.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter, Renderer $renderer) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('renderer')
    );
  }

  /**
   * Displays a Consent Agreement  revision.
   *
   * @param int $gdpr_consent_agreement_revision
   *   The Consent Agreement  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function revisionShow($gdpr_consent_agreement_revision) {
    $gdprConsentAgreement = $this->entityTypeManager
      ->getStorage('gdpr_consent_agreement')
      ->loadRevision($gdpr_consent_agreement_revision);
    $viewBuilder = $this->entityTypeManager
      ->getViewBuilder('gdpr_consent_agreement');

    return $viewBuilder->view($gdprConsentAgreement);
  }

  /**
   * Page title callback for a Consent Agreement  revision.
   *
   * @param int $gdpr_consent_agreement_revision
   *   The Consent Agreement  revision ID.
   *
   * @return string
   *   The page title.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function revisionPageTitle($gdpr_consent_agreement_revision) {
    $gdprConsentAgreement = $this->entityTypeManager
      ->getStorage('gdpr_consent_agreement')
      ->loadRevision($gdpr_consent_agreement_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $gdprConsentAgreement->label(),
      '%date' => $this->dateFormatter->format($gdprConsentAgreement->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Consent Agreement .
   *
   * @param \Drupal\gdpr_consent\Entity\ConsentAgreement $gdpr_consent_agreement
   *   A Consent Agreement object.
   *
   * @return array
   *   An array as expected by drupal_render().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function revisionOverview(ConsentAgreement $gdpr_consent_agreement) {
    $account = $this->currentUser();
    /** @var \Drupal\gdpr_consent\ConsentAgreementStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('gdpr_consent_agreement');

    $build['#title'] = $this->t('Revisions for %title', ['%title' => $gdpr_consent_agreement->title->value]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = $account->hasPermission('manage gdpr agreements');
    $delete_permission = $account->hasPermission('manage gdpr agreements');

    $rows = [];

    $vids = $storage->revisionIds($gdpr_consent_agreement);

    $latest_revision = TRUE;

    foreach (\array_reverse($vids) as $vid) {
      /** @var \Drupal\gdpr_consent\Entity\ConsentAgreement $revision */
      $revision = $storage->loadRevision($vid);

      $username = [
        '#theme' => 'username',
        '#account' => $revision->getRevisionUser(),
      ];

      // Use revision link to link to revisions that are not active.
      $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
      if ($vid !== $gdpr_consent_agreement->getRevisionId()) {
        $link = Link::fromTextAndUrl($date, new Url('entity.gdpr_consent_agreement.revision', [
          'gdpr_consent_agreement' => $gdpr_consent_agreement->id(),
          'gdpr_consent_agreement_revision' => $vid,
        ]))->toRenderable();
        $link = $this->renderer->renderPlain($link);
      }
      else {
        $link = $gdpr_consent_agreement->link($date);
      }

      $row = [];
      $column = [
        'data' => [
          '#type' => 'inline_template',
          '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
          '#context' => [
            'date' => $link,
            'username' => $this->renderer->renderPlain($username),
            'message' => [
              '#markup' => $revision->getRevisionLogMessage(),
              '#allowed_tags' => Xss::getHtmlTagList(),
            ],
          ],
        ],
      ];
      $row[] = $column;

      if ($latest_revision) {
        $row[] = [
          'data' => [
            '#prefix' => '<em>',
            '#markup' => $this->t('Current revision'),
            '#suffix' => '</em>',
          ],
        ];
        foreach ($row as &$current) {
          $current['class'] = ['revision-current'];
        }
        unset($current);
        $latest_revision = FALSE;
      }
      else {
        $links = [];
        if ($revert_permission) {
          $links['revert'] = [
            'title' => $this->t('Revert'),
            'url' => Url::fromRoute('entity.gdpr_consent_agreement.revision_revert', [
              'gdpr_consent_agreement' => $gdpr_consent_agreement->id(),
              'gdpr_consent_agreement_revision' => $vid,
            ]),
          ];
        }

        if ($delete_permission) {
          $links['delete'] = [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('entity.gdpr_consent_agreement.revision_delete', [
              'gdpr_consent_agreement' => $gdpr_consent_agreement->id(),
              'gdpr_consent_agreement_revision' => $vid,
            ]),
          ];
        }

        $row[] = [
          'data' => [
            '#type' => 'operations',
            '#links' => $links,
          ],
        ];
      }

      $rows[] = $row;
    }

    $build['gdpr_consent_agreement_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

  /**
   * Render My Agreements content.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user to show agreements for.
   *
   * @return array
   *   Renderable table of user agreements.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function myAgreements(AccountInterface $user) {
    $map = $this->entityFieldManager->getFieldMapByFieldType('gdpr_user_consent');
    $agreement_storage = $this->entityTypeManager->getStorage('gdpr_consent_agreement');
    $rows = [];

    foreach ($map as $entity_type => $fields) {
      $field_names = \array_keys($fields);

      foreach ($field_names as $field_name) {

        $ids = $this->entityTypeManager->getStorage($entity_type)->getQuery()
          ->condition($field_name . '.user_id', $user->id())
          ->execute();

        $entities = $this->entityTypeManager->getStorage($entity_type)
          ->loadMultiple($ids);

        foreach ($entities as $entity) {
          /** @var \Drupal\gdpr_consent\Entity\ConsentAgreementInterface $agreement */
          $agreement = $agreement_storage->loadRevision($entity->{$field_name}->target_revision_id);

          $link = $agreement->title->value;

          if ($agreement->access('view', $this->currentUser)) {
            $link = $agreement->toLink($agreement->title->value, 'revision')->toString();
          }

          $row = [];

          $row[] = [
            'data' => [
              '#markup' => $link,
            ],
          ];

          $row[] = [
            'data' => [
              '#markup' => $entity->{$field_name}->date,
            ],
          ];

          $rows[] = $row;
        }
      }
    }

    $header = ['Agreement', 'Date Agreed'];

    $build = [
      '#title' => 'Consent Agreements',
      'table' => [
        '#theme' => 'table',
        '#rows' => $rows,
        '#header' => $header,
        '#empty' => $this->t('You have not yet given any consent.'),
      ],
    ];
    return $build;
  }

}
