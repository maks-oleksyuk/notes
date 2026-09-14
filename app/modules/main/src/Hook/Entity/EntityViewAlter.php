<?php

declare(strict_types=1);

namespace Drupal\app_main\Hook\Entity;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Renders admin user view modes as a two-column table.
 */
#[Hook('entity_view_alter')]
final readonly class EntityViewAlter {

  public function __construct(
    private EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Implements hook_entity_view_alter().
   *
   * @param array<mixed, array<string, string|int|list<array<int, array<string, mixed>>>>> $build
   */
  public function __invoke(array &$build, FieldableEntityInterface $entity, EntityViewDisplayInterface $display): void {
    if (!\str_starts_with($display->getMode(), 'admin')) {
      return;
    }

    // Keep Field UI's weight order.
    $components = $display->getComponents();
    \uasort($components, SortArray::sortByWeightElement(...));

    $rows = [];
    foreach (\array_keys($components) as $name) {
      if (($build[$name] ?? []) === []) {
        continue;
      }

      if ($entity->hasField($name)) {
        if ($entity->get($name)->isEmpty()) {
          continue;
        }

        $label = (string) ($build[$name]['#title'] ?? $entity->get($name)->getFieldDefinition()->getLabel());
      }
      else {
        // Pseudo-field (e.g. added via hook_entity_extra_field_info()).
        $extraFields = $this->entityFieldManager->getExtraFields($entity->getEntityTypeId(), $entity->bundle());
        $label = (string) ($build[$name]['#title'] ?? $extraFields['display'][$name]['label'] ?? $name);
      }

      $value = $build[$name];
      $value['#label_display'] = 'hidden';
      unset($build[$name]);

      $rows[] = [
        ['header' => TRUE, 'data' => $label],
        ['data' => $value],
      ];
    }

    $build['admin_table'] = [
      '#type' => 'table',
      '#rows' => $rows,
      '#weight' => -100,
    ];
  }

}
