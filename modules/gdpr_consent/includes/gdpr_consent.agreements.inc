<?php

/**
 * @file
 * Pages file for the GDPR Consent module.
 */

/**
 * Page callback to list a user's consent agreements.
 *
 * @todo Finish this.
 */
function gdpr_consent_collected_agreements($account) {
  // Or load it with EFQ.
  $query = new EntityFieldQuery();
  $query->entityCondition('entity_type', 'gdpr_consent_agreement');

  $result = $query->execute();

  if (isset($result['gdpr_consent_agreement'])) {
    $consent_ids = array_keys($result['gdpr_consent_agreement']);
    entity_load('gdpr_consent_agreement', $consent_ids);
  }

  return MENU_ACCESS_DENIED;
}

/**
 * Callback for /admin/config/gdpr/agreements/{gdpr_consent_agreement ID} page.
 *
 * As we load the entity for display, we're responsible for invoking a number
 * of hooks in their proper order.
 *
 * @see hook_entity_prepare_view()
 * @see hook_entity_view()
 * @see hook_entity_view_alter()
 */
function gdpr_consent_agreement_view_entity($entity, $view_mode = 'default') {
  // Our entity type, for convenience.
  $entity_type = 'gdpr_consent_agreement';
  // Start setting up the content.
  $entity->content = array(
    '#view_mode' => $view_mode,
  );
  // Build fields content - this is where the Field API really comes in to play.
  // The task has very little code here because it all gets taken care of by
  // field module.
  // field_attach_prepare_view() lets the fields load any data they need
  // before viewing.
  field_attach_prepare_view($entity_type, array($entity->id => $entity),
    $view_mode);
  // We call entity_prepare_view() so it can invoke hook_entity_prepare_view()
  // for us.
  entity_prepare_view($entity_type, array($entity->id => $entity));
  // Now field_attach_view() generates the content for the fields.
  $entity->content += field_attach_view($entity_type, $entity, $view_mode);

  // OK, Field API done, now we can set up some of our own data.
  $entity->content['created'] = array(
    '#type' => 'item',
    '#title' => t('Created date'),
    '#markup' => format_date($entity->created),
  );
  $entity->content['description'] = array(
    '#type' => 'item',
    '#title' => t('Description'),
    '#markup' => $entity->description,
  );

  // Now to invoke some hooks. We need the language code for
  // hook_entity_view(), so let's get that.
  global $language;
  $langcode = $language->language;
  // And now invoke hook_entity_view().
  module_invoke_all('entity_view', $entity, $entity_type, $view_mode,
    $langcode);
  // Now invoke hook_entity_view_alter().
  drupal_alter(array('gdpr_consent_agreement_view_entity', 'entity_view'),
    $entity->content, $entity_type);

  // And finally return the content.
  return $entity->content;
}

/**
 * Callback for consent agreement revision page.
 */
function gdpr_consent_agreement_view_revision($revision_id) {
  $agreement = entity_revision_load('gdpr_consent_agreement', $revision_id);
  $output = gdpr_consent_agreement_view_entity($agreement);
  drupal_set_title(t('@title revision @id', array('@title' => $agreement->title, '@id' => $agreement->revision_id)));

  return $output;
}
