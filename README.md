# General Data Protection Regulation
## What it does
This module gives end user visibility to the data stored about himself/herself and aims to help site admins follows the guidelines and legislation set by the EU.

## Current features
* Allow logged in user to see all raw data stored about himself/herself (user entity)
* Allow user to initiate “forget me” action from site admins
* Checklist for site admin (recommend modules like cookie consent, check if there is privacy policy page etc)
    * Note, the viewing/editing the checklist requires permissions.
    * After enabling GDPR, see: /admin/people/permissions#module-checklistapi

## Planned features
* Make sure user can rectify all data about himself/herself
* Allow user to remove the account (content is not removed)
* More items and recommendations to checklist
* Add Drush hooks to sanitize data when syncing databases
* Make API for other contrib modules to announce user data stored

## Also needed
* Tests

## More information about GDPR
* https://www.eugdpr.org/
* [GDPR compliance in core](https://www.drupal.org/project/drupal/issues/2848974)
* https://www.itgovernance.co.uk/data-protection-dpa-and-eu-data-protection-regulation
* https://www.csoonline.com/article/3202771/data-protection/general-data-protection-regulation-gdpr-requirements-deadlines-and-facts.html

# Contribution
## On [drupal.org](https://www.drupal.org/project/gdpr)
Feel free to open new issues or comment on existing ones. New ideas and patches are welcome!

## On [github.com](https://github.com/brainsum/gdpr)
### Workflow:

* Create an issue for your feature/fix on [drupal.org](https://www.drupal.org/project/gdpr) if it doesn't already exist
* Fork the repo
* Create a new branch for your feature
    * Naming: base-branch/branch-name (e.g 8.x-1.x/my-feature-branch)
    * Please try to use a short and descriptive branch name
* Create a PR
    * Please include a link to the drupal.org issue in the comments
    * Please try to rebase your branch before creating the PR

### Additional requests:
* Please follow the drupal coding standards
    * See: [Coder module](https://www.drupal.org/project/coder)
