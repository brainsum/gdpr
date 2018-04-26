<?php

namespace Drupal\gdpr_consent;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\gdpr_consent\Entity\ConsentAgreementInterface;

/**
 * Defines the storage handler class for Consent Agreement entities.
 *
 * This extends the base storage class, adding required special handling for
 * Consent Agreement entities.
 *
 * @ingroup gdpr_consent
 */
interface ConsentAgreementStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Consent Agreement revision IDs for a specific Agreement.
   *
   * @param \Drupal\gdpr_consent\Entity\ConsentAgreementInterface $entity
   *   The Consent Agreement entity.
   *
   * @return int[]
   *   Consent Agreement revision IDs (in ascending order).
   */
  public function revisionIds(ConsentAgreementInterface $entity);

  /**
   * Gets a list of revision IDs for a given user as Consent Agreement author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Consent Agreement revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\gdpr_consent\Entity\ConsentAgreementInterface $entity
   *   The Consent Agreement entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(ConsentAgreementInterface $entity);

  /**
   * Unsets the language for all Consent Agreement with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
