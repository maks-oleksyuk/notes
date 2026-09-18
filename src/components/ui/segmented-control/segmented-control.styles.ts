import { tv } from 'tailwind-variants';

import type { VariantProps } from 'tailwind-variants';

export const segmentedControlVariants = tv({
  slots: {
    root: 'relative inline-flex gap-0.5 bg-gray-100 p-1 dark:bg-gray-800',
    control: 'relative flex-1',
    input: 'sr-only',
    label:
      'relative z-10 flex cursor-pointer items-center justify-center font-medium text-gray-600 transition-colors data-[active]:text-gray-900 dark:text-gray-400 dark:data-[active]:text-white',
    indicator:
      'absolute top-0 left-0 bg-white shadow-xs transition-[transform,width,height] duration-150 dark:bg-gray-700',
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
