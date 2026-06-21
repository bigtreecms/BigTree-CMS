import type { Meta, StoryObj } from "@storybook/react-vite";

import { TextInput } from "./TextInput";

/**
 * Bare single-line input applying the canonical `inputClass` to a native
 * `<input>`, forwarding every native prop (and a `ref`). Use it for a control
 * without label chrome; reach for `TextField` when you want the label too.
 */
const meta = {
	title: "UI/Form/TextInput",
	component: TextInput,
	tags: ["autodocs"],
	args: { placeholder: "you@example.com" },
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 320 }}>
				<Story />
			</div>
		),
	],
} satisfies Meta<typeof TextInput>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithValue: Story = {
	args: { defaultValue: "hello@bigtreecms.org" },
};

export const Disabled: Story = {
	args: { defaultValue: "Read only", disabled: true },
};

export const Password: Story = {
	args: { type: "password", placeholder: "••••••••" },
};

export const States: Story = {
	render: (args) => (
		<div className="flex flex-col gap-3">
			<TextInput {...args} placeholder="Empty / placeholder" />
			<TextInput {...args} defaultValue="With a value" />
			<TextInput {...args} defaultValue="Disabled" disabled />
		</div>
	),
};
