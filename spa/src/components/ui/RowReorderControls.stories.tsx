import type { Meta, StoryObj } from "@storybook/react-vite";

import { RowReorderControls } from "./RowReorderControls";

const noop = () => {};

/**
 * `RowReorderControls` is the move-up / move-down / remove icon cluster shared
 * by the reorderable list editors (the schema builders). `isFirst`/`isLast`
 * disable the matching move button; remove is the danger tone.
 */
const meta = {
	title: "UI/RowReorderControls",
	component: RowReorderControls,
	tags: ["autodocs"],
	args: {
		onMoveUp: noop,
		onMoveDown: noop,
		onRemove: noop,
		isFirst: false,
		isLast: false,
		itemLabel: "sub-field",
	},
} satisfies Meta<typeof RowReorderControls>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Playground: Story = {};

/** The first row can't move up. */
export const FirstRow: Story = {
	args: { isFirst: true },
};

/** The last row can't move down. */
export const LastRow: Story = {
	args: { isLast: true },
};

/** A single row is both first and last, so only remove is enabled. */
export const OnlyRow: Story = {
	args: { isFirst: true, isLast: true },
};
