import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { Checkbox } from "./Checkbox";

/**
 * Labeled checkbox — the single source of truth for the
 * `<label><input type="checkbox" />text</label>` pattern across the admin. Pass
 * `align="start"` to top-align the box against a multi-line label.
 */
const meta = {
	title: "UI/Form/Checkbox",
	component: Checkbox,
	tags: ["autodocs"],
	// `onChange` is supplied by the stateful `render` below; the no-op default
	// here just satisfies Storybook's required-args typing.
	args: { label: "Send a notification email", checked: false, onChange: () => {} },
	render: (args) => {
		const [checked, setChecked] = useState(args.checked);

		return <Checkbox {...args} checked={checked} onChange={setChecked} />;
	},
} satisfies Meta<typeof Checkbox>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Unchecked: Story = {};

export const Checked: Story = {
	args: { checked: true },
};

export const Disabled: Story = {
	args: { label: "Locked setting", checked: true, disabled: true },
};

/** `align="start"` keeps the box at the top when the label wraps. */
export const MultiLineLabel: Story = {
	args: {
		align: "start",
		label: "Enable advanced caching for this page — recommended for high-traffic pages that change infrequently.",
	},
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 280 }}>
				<Story />
			</div>
		),
	],
};
