import { tv } from 'tailwind-variants';

import type { VariantProps } from 'tailwind-variants';

export const segmentedControlVariants = tv({
  slots: {
    root: 'relative inline-flex gap-0.5 bg-gray-100 p-1 dark:bg-gray-800',
    control: 'relative flex-1',
    input: 'peer sr-only',
    label: [
      'relative z-10 flex cursor-pointer items-center justify-center font-medium text-gray-600 transition-colors dark:text-gray-400',
      // active: selected and not disabled
      'data-active:text-gray-900 dark:data-active:text-white',
      // disabled: whole control or a single item
      'data-disabled:cursor-not-allowed data-disabled:opacity-50',
      // read-only
      'data-read-only:cursor-default',
      // Before hydration there is no indicator (Mantine positions it with JS),
      // so the checked label paints the background itself. Keyed on the
      // checked input, not data-active: disabled labels have no data-active.
      'peer-checked:not-in-data-initialized:bg-white peer-checked:not-in-data-initialized:shadow-xs dark:peer-checked:not-in-data-initialized:bg-gray-700',
    ],
    indicator: [
      'absolute top-0 left-0 bg-white shadow-xs dark:bg-gray-700',
      // Animate only after Mantine sets data-initialized, otherwise the first
      // positioning slides in from the top-left corner. The duration is gated
      // too: transition-property defaults to `all`, so it alone animates.
      'data-initialized:transition-all data-initialized:duration-150',
    ],
    innerLabel: 'flex items-center gap-2 whitespace-nowrap',
  },
  variants: {
    size: {
      xs: { label: 'h-6 px-2 text-xs' },
      sm: { label: 'h-7 px-2.5 text-sm' },
      md: { label: 'h-8 px-3 text-sm' },
      lg: { label: 'h-9 px-4 text-base' },
      xl: { label: 'h-10 px-5 text-lg' },
    },
    radius: {
      xs: { root: 'rounded-sm', label: 'rounded-xs', indicator: 'rounded-xs' },
      sm: { root: 'rounded-md', label: 'rounded-sm', indicator: 'rounded-sm' },
      md: { root: 'rounded-lg', label: 'rounded-md', indicator: 'rounded-md' },
      lg: { root: 'rounded-xl', label: 'rounded-lg', indicator: 'rounded-lg' },
      xl: {
        root: 'rounded-full',
        label: 'rounded-full',
        indicator: 'rounded-full',
      },
    },
  },
  defaultVariants: {
    size: 'md',
    radius: 'xl',
  },
});

export type SegmentedControlVariants = VariantProps<
  typeof segmentedControlVariants
>;
