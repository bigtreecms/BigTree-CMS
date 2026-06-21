import type { Meta, StoryObj } from "@storybook/react-vite";

import { Select } from "./Select";

/**
 * Bare `<select>` sharing the canonical `inputClass` so dropdowns line up with
 * text inputs. Render `<option>`s as children; use `SelectField` for the label
 * wrapper.
 */
const meta = {
	title: "UI/Form/Select",
	component: Select,
	tags: ["autodocs"],
	args: { "aria-label": "Status" },
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 320 }}>
				<Story />
			</div>
		),
	],
	render: (args) => (
		<Select {...args}>
			<option value="draft">Draft</option>
			<option value="published">Published</option>
			<option value="archived">Archived</option>
		</Select>
	),
} satisfies Meta<typeof Select>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithValue: Story = {
	args: { defaultValue: "published" },
};

export const Disabled: Story = {
	args: { defaultValue: "published", disabled: true },
};
