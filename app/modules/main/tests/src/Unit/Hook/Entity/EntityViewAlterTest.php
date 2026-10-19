<?php

declare(strict_types=1);

namespace Drupal\Tests\app_main\Unit\Hook\Entity;

use Drupal\app_main\Hook\Entity\EntityViewAlter;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\UnitTestCase;

/**
 * Tests rendering of admin view modes as a table.
 */
#[Group('app_main')]
#[CoversClass(EntityViewAlter::class)]
final class EntityViewAlterTest extends UnitTestCase {

  /**
   * Leaves the build untouched for non-admin view modes.
   */
  public function testIgnoresNonAdminViewMode(): void {
    $build = ['body' => ['#markup' => 'Body']];

    $this->alter($build, $this->entity([]), $this->display('full', ['body' => ['weight' => 0]]));

    $this->assertSame(['body' => ['#markup' => 'Body']], $build);
  }

  /**
   * Moves fields into a two-column table ordered by weight.
   */
  public function testBuildsTableInWeightOrder(): void {
    $build = [
      'body' => ['#markup' => 'Body'],
      'title' => ['#markup' => 'Title', '#title' => 'Custom title'],
    ];
    $entity = $this->entity(['body' => 'Body label', 'title' => 'Title label']);
    $display = $this->display('admin', [
      'body' => ['weight' => 5],
      'title' => ['weight' => 1],
    ]);

    $this->alter($build, $entity, $display);

    $this->assertSame(['admin_table'], \array_keys($build));
    $this->assertSame('table', $build['admin_table']['#type']);
    $this->assertSame(-100, $build['admin_table']['#weight']);
    $this->assertSame([
      [
        ['header' => TRUE, 'data' => 'Custom title'],
        ['data' => ['#markup' => 'Title', '#title' => 'Custom title', '#label_display' => 'hidden']],
      ],
      [
        ['header' => TRUE, 'data' => 'Body label'],
        ['data' => ['#markup' => 'Body', '#label_display' => 'hidden']],
      ],
    ], $build['admin_table']['#rows']);
  }

  /**
   * Skips components without output and fields without values.
   */
  public function testSkipsEmptyComponents(): void {
    $build = [
      'no_output' => [],
      'empty_field' => ['#markup' => 'Stale'],
    ];
    $entity = $this->entity(['no_output' => 'No output', 'empty_field' => NULL]);
    $display = $this->display('admin_compact', [
      'no_output' => ['weight' => 0],
      'empty_field' => ['weight' => 1],
    ]);

    $this->alter($build, $entity, $display);

    $this->assertSame([], $build['admin_table']['#rows']);
    $this->assertSame(['#markup' => 'Stale'], $build['empty_field']);
  }

  /**
   * Labels pseudo-fields from extra field info, falling back to the name.
   */
  public function testPseudoFieldLabels(): void {
    $build = [
      'links' => ['#markup' => 'Links'],
      'unknown' => ['#markup' => 'Unknown'],
    ];
    $display = $this->display('admin', [
      'links' => ['weight' => 0],
      'unknown' => ['weight' => 1],
    ]);
    $fieldManager = $this->createStub(EntityFieldManagerInterface::class);
    $fieldManager->method('getExtraFields')->willReturn([
      'display' => ['links' => ['label' => 'Links label']],
    ]);

    $this->alter($build, $this->entity([]), $display, $fieldManager);

    $labels = \array_map(static fn (array $row): string => $row[0]['data'], $build['admin_table']['#rows']);
    $this->assertSame(['Links label', 'unknown'], $labels);
  }

  /**
   * Runs the hook.
   *
   * @param array<mixed> $build
   *   The render array to alter.
   */
  private function alter(array &$build, FieldableEntityInterface $entity, EntityViewDisplayInterface $display, ?EntityFieldManagerInterface $fieldManager = NULL): void {
    $hook = new EntityViewAlter($fieldManager ?? $this->createStub(EntityFieldManagerInterface::class));
    $hook($build, $entity, $display);
  }

  /**
   * Creates an entity stub.
   *
   * @param array<string, string|null> $fields
   *   Field labels keyed by field name; NULL marks an empty field.
   */
  private function entity(array $fields): FieldableEntityInterface {
    $items = [];
    foreach ($fields as $name => $label) {
      $definition = $this->createStub(FieldDefinitionInterface::class);
      $definition->method('getLabel')->willReturn($label ?? '');
      $item = $this->createStub(FieldItemListInterface::class);
      $item->method('isEmpty')->willReturn($label === NULL);
      $item->method('getFieldDefinition')->willReturn($definition);
      $items[$name] = $item;
    }

    $entity = $this->createStub(FieldableEntityInterface::class);
    $entity->method('hasField')->willReturnCallback(static fn (string $name): bool => isset($items[$name]));
    $entity->method('get')->willReturnCallback(static fn (string $name): FieldItemListInterface => $items[$name]);
    $entity->method('getEntityTypeId')->willReturn('user');
    $entity->method('bundle')->willReturn('user');
    return $entity;
  }

  /**
   * Creates a view display stub.
   *
   * @param array<string, array{weight: int}> $components
   *   Display components keyed by name.
   */
  private function display(string $mode, array $components): EntityViewDisplayInterface {
    $display = $this->createStub(EntityViewDisplayInterface::class);
    $display->method('getMode')->willReturn($mode);
    $display->method('getComponents')->willReturn($components);
    return $display;
  }

}
