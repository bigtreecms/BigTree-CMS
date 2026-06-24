import type { Meta, StoryObj } from "@storybook/react-vite";

import { Field } from "./Field";
import { TextInput } from "./TextInput";

/**
 * Label + optional hint/error wrapper shared by the form-control primitives and
 * any one-off field needing the same chrome. Renders a `<label>`, so the control
 * passed as `children` is associated automatically. Use it directly when you
 * need a `ref` or native attribute on the control that the `*Field` wrappers
 * don't forward.
 */
const meta = {
	title: "UI/Form/Field",
	component: Field,
	tags: ["autodocs"],
	args: {
		label: "Email address",
		children: <TextInput placeholder="you@example.com" />,
	},
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 360 }}>
				<Story />
			</div>
		),
	],
} satisfies Meta<typeof Field>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithHint: Story = {
	args: { hint: "We'll only use this for password resets." },
};

export const Required: Story = {
	args: { required: true },
};

export const WithError: Story = {
	args: { error: "Enter a valid email address." },
};

/** A short hint can sit inline on the label line (the resource-designer treatment). */
export const InlineHint: Story = {
	args: { label: "Slug", inlineHint: "lowercase, no spaces" },
};

/**
 * `size="sm"` is the denser label used by the resource designer (`ControlShell`
 * composes this). Pair with an `inlineHint` and a below `hint`.
 */
export const Small: Story = {
	args: {
		label: "Column",
		size: "sm",
		inlineHint: "optional",
		hint: "Leave blank to use the field key.",
	},
};
