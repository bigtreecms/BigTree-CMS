import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { SelectField } from "./SelectField";

/**
 * Labeled `<select>` — the dropdown counterpart to `TextField`, composed on
 * `Field` for shared label / hint / error chrome.
 */
const options = [
	{ value: "draft", label: "Draft" },
	{ value: "published", label: "Published" },
	{ value: "archived", label: "Archived" },
];

const meta = {
	title: "UI/Form/SelectField",
	component: SelectField,
	tags: ["autodocs"],
	// `onChange` is supplied by the stateful `render` below; the no-op default
	// here just satisfies Storybook's required-args typing.
	args: { label: "Status", value: "draft", onChange: () => {}, options },
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 360 }}>
				<Story />
			</div>
		),
	],
	render: (args) => {
		const [value, setValue] = useState(args.value);

		return <SelectField {...args} value={value} onChange={setValue} />;
	},
} satisfies Meta<typeof SelectField>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithHint: Story = {
	args: { hint: "Only published pages appear on the live site." },
};

export const Required: Story = {
	args: { required: true },
};

export const WithError: Story = {
	args: { error: "Pick a status." },
};

export const Disabled: Story = {
	args: { disabled: true },
};

export const Dense: Story = {
	args: { dense: true },
};

export const SizeSmall: Story = {
	args: { size: "sm" },
};
