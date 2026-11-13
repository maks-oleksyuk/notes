import {
  HeadlessMantineProvider,
  SegmentedControl as MantineSegmentedControl,
} from '@mantine/core';

import { segmentedControlVariants } from './segmented-control.styles';

import type {
  SegmentedControlProps as MantineSegmentedControlProps,
  SegmentedControlStylesNames,
} from '@mantine/core';
import type { SegmentedControlVariants } from './segmented-control.styles';

type SegmentedControlProps = Omit<
  MantineSegmentedControlProps,
  keyof SegmentedControlVariants | 'classNames'
> &
  SegmentedControlVariants & {
    classNames?: Partial<Record<SegmentedControlStylesNames, string>>;
  };

export function SegmentedControl({
  size,
  radius,
  className,
  classNames,
  ...props
}: SegmentedControlProps) {
  const styles = segmentedControlVariants({ size, radius });
  const rootClassName = styles.root({ className });
  const slotClassNames = {
    control: styles.control({ className: classNames?.control }),
    input: styles.input({ className: classNames?.input }),
    label: styles.label({ className: classNames?.label }),
    indicator: styles.indicator({ className: classNames?.indicator }),
    innerLabel: styles.innerLabel({ className: classNames?.innerLabel }),
  };

  return (
    <HeadlessMantineProvider>
      <MantineSegmentedControl
        {...props}
        className={rootClassName}
        classNames={slotClassNames}
      />
    </HeadlessMantineProvider>
  );
}
