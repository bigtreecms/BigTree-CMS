import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { Radio } from "./Radio";

/**
 * Labeled radio — the single source of truth for the
 * `<label><input type="radio" />text</label>` pattern across the admin. Groups
 * own their layout and render one `Radio` per option; pass `align="start"` to
 * top-align the dot against a multi-line label.
 */
const meta = {
	title: "UI/Form/Radio",
	component: Radio,
	tags: ["autodocs"],
	// `onChange` is supplied by the stateful `render` below; the no-op default
	// here just satisfies Storybook's required-args typing.
	args: { label: "Declarative", name: "demo", value: "a", checked: true, onChange: () => {} },
	render: (args) => {
		const [value, setValue] = useState(args.checked ? args.value : "");

		return (
			<Radio {...args} checked={value === args.value} onChange={() => setValue(args.value)} />
		);
	},
} satisfies Meta<typeof Radio>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Checked: Story = {};

export const Unchecked: Story = {
	args: { checked: false },
};

export const Disabled: Story = {
	args: { label: "Locked option", disabled: true },
};

/** `size="sm"` is the compact dot for dense rows (e.g. permission grids). */
export const Small: Story = {
	args: { size: "sm", label: "Read-only" },
};

/** `align="start"` keeps the dot at the top when the label wraps. */
export const MultiLineLabel: Story = {
	args: {
		align: "start",
		label: "JavaScript module — write code that draws the field. Runs locally in the SPA; you implicitly trust your own code.",
	},
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 280 }}>
				<Story />
			</div>
		),
	],
};
