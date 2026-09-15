import {
  HeadlessMantineProvider,
  SegmentedControl as MantineSegmentedControl,
} from '@mantine/core';

import { segmentedControlVariants } from './segmented-control.styles';

import type { SegmentedControlProps as MantineSegmentedControlProps } from '@mantine/core';
import type { SegmentedControlVariants } from './segmented-control.styles';

type SegmentedControlProps = Omit<
  MantineSegmentedControlProps,
  keyof SegmentedControlVariants
> &
  SegmentedControlVariants;

export function SegmentedControl(props: SegmentedControlProps) {
  const classNames = segmentedControlVariants(props);

  return (
    <HeadlessMantineProvider>
      <MantineSegmentedControl
        {...props}
        className={classNames.root({ className: props.className })}
        classNames={{
          control: classNames.control(),
          input: classNames.input(),
          label: classNames.label(),
          indicator: classNames.indicator(),
          innerLabel: classNames.innerLabel(),
        }}
      />
    </HeadlessMantineProvider>
  );
}
