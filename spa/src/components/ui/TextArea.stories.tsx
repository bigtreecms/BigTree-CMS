import type { Meta, StoryObj } from "@storybook/react-vite";

import { TextArea } from "./TextArea";

/**
 * Bare multi-line input sharing the canonical `inputClass`, with a resizable
 * vertical handle. Pass `rows` to size it; append `font-mono` via `className`
 * for code/markup entry.
 */
const meta = {
	title: "UI/Form/TextArea",
	component: TextArea,
	tags: ["autodocs"],
	args: { placeholder: "Write a description…", rows: 4 },
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 420 }}>
				<Story />
			</div>
		),
	],
} satisfies Meta<typeof TextArea>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithValue: Story = {
	args: {
		defaultValue: "BigTree is a flexible, easy-to-use CMS for developers and content editors.",
	},
};

export const Monospace: Story = {
	args: {
		className: "font-mono",
		defaultValue: '{\n\t"key": "value"\n}',
		rows: 6,
	},
};

export const Disabled: Story = {
	args: { defaultValue: "Read only", disabled: true },
};
