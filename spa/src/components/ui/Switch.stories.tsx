import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { Switch } from "./Switch";

/**
 * On/off toggle switch — the sliding-knob `aria-pressed` toggle. Reach for it
 * when the boolean is a prominent, immediate setting (vs. the inline-flow
 * `Checkbox`, the default for form booleans).
 */
const meta = {
	title: "UI/Form/Switch",
	component: Switch,
	tags: ["autodocs"],
	// `onChange` is supplied by the stateful `render` below; the no-op default
	// here just satisfies Storybook's required-args typing.
	args: { label: "Send invitation email", on: false, onChange: () => {} },
	render: (args) => {
		const [on, setOn] = useState(args.on);

		return <Switch {...args} on={on} onChange={setOn} />;
	},
} satisfies Meta<typeof Switch>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Off: Story = {};

export const On: Story = {
	args: { on: true },
};

export const Disabled: Story = {
	args: { on: true, disabled: true },
};
