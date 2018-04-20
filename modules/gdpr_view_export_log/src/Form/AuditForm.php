<?php

namespace Drupal\gdpr_view_export_log\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for creating an export audit.
 *
 * @package Drupal\gdpr_view_export_log\Form
 */
class AuditForm extends ContentEntityForm {

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['#title'] = $this->t('Please provide some information about your export.');

    $form['intro'] = [
      '#markup' => 'To continue, please enter details about how this export will be used.',
    ];

    $session = $this->getRequest()->getSession();

    $form['continue_url'] = [
      '#type' => 'hidden',
      '#default_value' => $session->get('gdpr_export_audit_continue'),
    ];

    $form['filename'] = [
      '#type' => 'hidden',
      '#default_value' => $session->get('gdpr_export_audit_file'),
    ];

    $form['view'] = [
      '#type' => 'hidden',
      '#default_value' => $session->get('gdpr_export_audit_view'),
    ];

    $session->remove('gdpr_export_audit_view');
    $session->remove('gdpr_export_audit_file');
    $session->remove('gdpr_export_audit_continue');

    $form['actions']['submit']['#value'] = $this->t('Continue');
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    $entity->set('owner', \Drupal::currentUser()->id());
    parent::save($form, $form_state);

    $url = urldecode($form_state->getValue('continue_url'));
    if (strpos($url, '?') > -1) {
      $url .= '&audited=1';
    }
    else {
      $url .= '?audited=1';
    }

    $this->getRequest()->getSession()->set('gdpr_audit_id', $entity->id());
    $form_state->setRedirectUrl(Url::fromUserInput($url));
  }

}
