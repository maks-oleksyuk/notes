<?php

declare(strict_types=1);

namespace Drupal\Tests\app_main\Unit\Hook\System;

use Drupal\app_main\Hook\System\RuntimeRequirementsAlter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the status report requirements alter hook.
 */
#[Group('app_main')]
#[CoversClass(RuntimeRequirementsAlter::class)]
final class RuntimeRequirementsAlterTest extends UnitTestCase {

  /**
   * Removes only the "update status" requirement.
   */
  public function testRemovesUpdateStatus(): void {
    $requirements = [
      'update status' => ['title' => 'Update notifications'],
      'php' => ['title' => 'PHP'],
    ];

    (new RuntimeRequirementsAlter())($requirements);

    $this->assertSame(['php' => ['title' => 'PHP']], $requirements);
  }

  /**
   * Leaves requirements untouched when "update status" is absent.
   */
  public function testWithoutUpdateStatus(): void {
    $requirements = ['php' => ['title' => 'PHP']];

    (new RuntimeRequirementsAlter())($requirements);

    $this->assertSame(['php' => ['title' => 'PHP']], $requirements);
  }

}
