<?php

namespace Drupal\gdpr_consent\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Consent Agreement entities.
 *
 * @ingroup gdpr_consent
 */
interface ConsentAgreementInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * Gets the Consent Agreement name.
   *
   * @return string
   *   Name of the Consent Agreement.
   */
  public function getName();

  /**
   * Sets the Consent Agreement name.
   *
   * @param string $name
   *   The Consent Agreement name.
   *
   * @return \Drupal\gdpr_consent\Entity\ConsentAgreementInterface
   *   The called Consent Agreement entity.
   */
  public function setName($name);

  /**
   * Gets the Consent Agreement creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Consent Agreement.
   */
  public function getCreatedTime();

  /**
   * Sets the Consent Agreement creation timestamp.
   *
   * @param int $timestamp
   *   The Consent Agreement creation timestamp.
   *
   * @return \Drupal\gdpr_consent\Entity\ConsentAgreementInterface
   *   The called Consent Agreement entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Consent Agreement published status indicator.
   *
   * Unpublished Consent Agreement are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Consent Agreement is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Consent Agreement.
   *
   * @param bool $published
   *   TRUE to set this Consent Agreement to published, FALSE to unpublished.
   *
   * @return \Drupal\gdpr_consent\Entity\ConsentAgreementInterface
   *   The called Consent Agreement entity.
   */
  public function setPublished($published);

  /**
   * Gets the Consent Agreement revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Consent Agreement revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\gdpr_consent\Entity\ConsentAgreementInterface
   *   The called Consent Agreement entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Consent Agreement revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Consent Agreement revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\gdpr_consent\Entity\ConsentAgreementInterface
   *   The called Consent Agreement entity.
   */
  public function setRevisionUserId($uid);

}
