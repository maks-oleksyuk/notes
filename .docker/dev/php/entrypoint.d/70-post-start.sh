#!/bin/sh
set -e

cd "$APP_BASE_DIR"

if task drush -- status --field=bootstrap 2>/dev/null | grep -q Successful; then
  task drush -- deploy -y
else
  task drush -- site:install -y --existing-config ${DRUPAL_ADMIN_PASSWORD:+--account-pass="$DRUPAL_ADMIN_PASSWORD"}
  task drush -- deploy:mark-complete -y
fi
