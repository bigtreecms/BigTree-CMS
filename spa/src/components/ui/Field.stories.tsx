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
