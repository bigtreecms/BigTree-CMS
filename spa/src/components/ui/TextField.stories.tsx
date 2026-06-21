import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { TextField } from "./TextField";

/**
 * Labeled single-line text input — the standard control for admin forms.
 * Composes `Field` (label / hint / error) around the bare `TextInput`.
 */
const meta = {
	title: "UI/Form/TextField",
	component: TextField,
	tags: ["autodocs"],
	// `onChange` is supplied by the stateful `render` below; the no-op default
	// here just satisfies Storybook's required-args typing.
	args: { label: "Page title", value: "", onChange: () => {}, placeholder: "Untitled page" },
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 360 }}>
				<Story />
			</div>
		),
	],
	render: (args) => {
		const [value, setValue] = useState(args.value);

		return <TextField {...args} value={value} onChange={setValue} />;
	},
} satisfies Meta<typeof TextField>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithHint: Story = {
	args: { hint: "Shown in the browser tab and search results." },
};

export const Required: Story = {
	args: { required: true },
};

export const WithError: Story = {
	args: { value: "", error: "A title is required." },
};

export const Disabled: Story = {
	args: { value: "Read only", disabled: true },
};
