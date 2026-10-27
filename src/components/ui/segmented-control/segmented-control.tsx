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

export function SegmentedControl(props: SegmentedControlProps) {
  const classNames = segmentedControlVariants(props);

  return (
    <HeadlessMantineProvider>
      <MantineSegmentedControl
        {...props}
        className={classNames.root({ className: props.className })}
        classNames={{
          control: classNames.control({ className: props.classNames?.control }),
          input: classNames.input({ className: props.classNames?.input }),
          label: classNames.label({ className: props.classNames?.label }),
          indicator: classNames.indicator({
            className: props.classNames?.indicator,
          }),
          innerLabel: classNames.innerLabel({
            className: props.classNames?.innerLabel,
          }),
        }}
      />
    </HeadlessMantineProvider>
  );
}
