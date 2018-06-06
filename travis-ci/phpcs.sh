#!/usr/bin/env bash
if [ "$PHPCS_WARNINGS" != "warnings" ]; then
  - $DRUPAL_BUILD_ROOT/drupal/vendor/bin/phpcs $TRAVIS_BUILD_DIR -p --ignore=$TRAVIS_BUILD_DIR/README.md --standard=Drupal --colors
else
  - $DRUPAL_BUILD_ROOT/drupal/vendor/bin/phpcs $TRAVIS_BUILD_DIR -p --ignore=$TRAVIS_BUILD_DIR/README.md --standard=DrupalPractice --colors
fi