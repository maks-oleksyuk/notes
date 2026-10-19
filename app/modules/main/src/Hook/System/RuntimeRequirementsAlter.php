<?php

declare(strict_types=1);

namespace Drupal\app_main\Hook\System;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hides the "Update notifications" warning on the status report.
 */
#[Hook('runtime_requirements_alter')]
final class RuntimeRequirementsAlter {

  /**
   * Implements hook_runtime_requirements_alter().
   *
   * @param array<string, mixed> $requirements
   */
  public function __invoke(array &$requirements): void {
    unset($requirements['update status']);
  }

}
