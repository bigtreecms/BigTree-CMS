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
			<TextInput {...args} disabled defaultValue="Disabled" />
		</div>
	),
};

/**
 * `dense` trims the vertical padding for space-constrained sections (e.g. the
 * module designer); `mono` switches to a monospace, slightly smaller face for
 * identifier / code entry.
 */
export const Variants: Story = {
	render: (args) => (
		<div className="flex flex-col gap-3">
			<TextInput {...args} defaultValue="Default" />
			<TextInput {...args} dense defaultValue="Dense" />
			<TextInput {...args} compact className="w-full" defaultValue="Compact" />
			<TextInput {...args} mono defaultValue="module_route" />
			<TextInput {...args} dense mono defaultValue="module_route" />
		</div>
	),
};

/**
 * `compact` is the tightest tier (`px-2 py-1 text-[12.5px]`) for inline grid /
 * toolbar controls — it carries no width, so pass one via `className`.
 */
export const Compact: Story = {
	args: { compact: true, className: "w-full", defaultValue: "Compact field" },
};
