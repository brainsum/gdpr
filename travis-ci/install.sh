#!/usr/bin/env bash

echo "# Preparing GIT repos"

# Remove the git details from our repo so we can treat it as a path.
cd $TRAVIS_BUILD_DIR
rm .git -rf

# Create our main Drupal project.
echo "# Creating Drupal project"
composer create-project drupal-composer/drupal-project:8.x-dev $DRUPAL_BUILD_ROOT/drupal --stability dev --no-interaction --no-install
cd $DRUPAL_BUILD_ROOT/drupal

# Set our drupal core version.
composer require drupal/core $DRUPAL_CORE --no-update
composer require drupal/coder --no-update --dev

# We do not need drupal console and drush (required by drupal-project) for tests.
composer remove drupal/console drush/drush --no-update

# Add our repositories for gdpr, as well as re-adding the Drupal package repo.
echo "# Configuring package repos"
composer config repositories.0 path $TRAVIS_BUILD_DIR
composer config repositories.1 composer https://packages.drupal.org/8
composer config extra.enable-patching true

# Now require contacts which will pull itself from the paths.
echo "# Requiring gdpr"
composer require drupal/gdpr dev-master
