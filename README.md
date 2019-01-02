General Data Protection Regulation
==================================

[![Build Status](https://travis-ci.org/brainsum/gdpr.svg?branch=8.x-1.x)](https://travis-ci.org/brainsum/gdpr)


INTRODUCTION
------------

The General Data Protection Regulation module gives end user visibility to the
data stored about themself and aims to help site admins follow the guidelines
and legislation set by the EU.

Please note:
Installing and using this module pack does not mean the site becomes GDPR
compliant. GDPR affects the whole organisation, this module aims to help to
understand its Drupal relations and tries to provide helper tools to make the
site GDPR compliant.

 * For a full description of the module visit:
   https://www.drupal.org/project/gdpr

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/gdpr

For information about GDPR:

 * EU GDPR - https://www.eugdpr.org/
 * GDPR compliance in core - https://www.drupal.org/project/drupal/issues/2848974
 * ITGovernance Article - https://www.itgovernance.co.uk/data-protection-dpa-and-eu-data-protection-regulation
 * CSO Article - https://www.csoonline.com/article/3202771/data-protection/general-data-protection-regulation-gdpr-requirements-deadlines-and-facts.html


REQUIREMENTS
------------

Since PHP 5.6 reached its end of life on 31 Dec. 2018, for security reasons,
the required minimum PHP version has been changed to 7.1.
Read more here: http://php.net/supported-versions.php

This module requires the following outside of Drupal core.

 * Checklist API - https://www.drupal.org/project/checklistapi

INSTALLATION
------------

 * Install the General Data Protection Regulation module as you would normally
   install a contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module and its
       dependencies.
    2. Navigate to Administration > People > Permissions and enable permissions
       for appropriate users.
    3. Navigate to Administration > Configuration > GDPR > Checklist. A
       checklist is available to help make sure the site is GDPR compliant.
    4. Navigate to Administration > Configuration > GDPR > SQL Dump settings to
       configure which data is to be sanitized. Check the checkboxes for each
       table column containing sensitive data. Save configuration.

Current Features:

 * Allow logged in user to see all raw data stored about themself (user
   entity)
 * Allow user to initiate "forget me" action from site administrators
 * Checklist for site admin (recommend modules like cookie consent, check if
   there is privacy policy page, etc.)

Planned Features:

 * Make sure user can rectify all data about themself
 * Allow user to remove the account (content is not removed)
 * More items and recommendations to checklist
 * Add Drush hooks to sanitize data when syncing databases
 * Make API for other contrib modules to announce user data stored


MAINTAINERS
-----------

 * Levente Besenyei (lbesenyei) - https://www.drupal.org/u/lbesenyei
 * Peter Pónya (pedrop) - https://www.drupal.org/u/pedrop
 * Máté Havelant (mhavelant) - https://www.drupal.org/u/mhavelant
 * Marko Korhonen (back-2-95) - https://www.drupal.org/u/back-2-95
 * Roni Kantis (bfr) - https://www.drupal.org/u/bfr

Supporting organizations:

Initial kick-off, MVP for D7, funding further development

 * Druid - https://www.drupal.org/druid

Porting MVP to D8, further development

 * Brainsum - https://www.drupal.org/brainsum


CONTRIBUTION
------------


On drupal.org - https://www.drupal.org/project/gdpr
---------------------------------------------------
Feel free to open new issues or comment on existing ones. New ideas and patches are welcome!


On github.com - https://github.com/brainsum/gdpr
------------------------------------------------
Workflow:

* Create an issue for your feature/fix on drupal.org if it doesn't already exist
* Fork the repo
* Create a new branch for your feature
    * Naming: base-branch/type/branch-name
        * e.g 8.x-1.x/feature/my-feature-branch
        * e.g 7.x-1.x/fix/typo-fixes
    * Please try to use a short and descriptive branch name
* Create a PR
    * Please include a link to the drupal.org issue in the comments
    * Please try to rebase your branch before creating the PR

Additional requests:

* Please follow the drupal coding standards
    * See: Coder module - https://www.drupal.org/project/coder
