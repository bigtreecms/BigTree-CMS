import { useState } from "react";

import type { Meta, StoryObj } from "@storybook/react-vite";

import { JsonField } from "./JsonField";

const meta: Meta<typeof JsonField> = {
	title: "UI/JsonField",
	component: JsonField,
};

export default meta;
type Story = StoryObj<typeof JsonField>;

const Controlled = (args: Parameters<typeof JsonField>[0]) => {
	const [value, setValue] = useState(args.value);

	return <JsonField {...args} value={value} onChange={setValue} />;
};

/** Valid JSON object — editable, commits on blur. */
export const ValidObject: Story = {
	render: (args) => <Controlled {...args} />,
	args: {
		label: "Config",
		value: { key: "value", count: 42 },
		rows: 5,
	},
};

/** Empty string in the textarea is treated as `{}` on commit. */
export const EmptyFallsBackToObject: Story = {
	render: (args) => <Controlled {...args} />,
	args: {
		label: "Config",
		value: {},
		rows: 5,
	},
};

/** Shows a parse-error inline after blurring with invalid JSON. */
export const ParseError: Story = {
	render: (args) => <Controlled {...args} />,
	args: {
		label: "Config",
		value: { existing: true },
		rows: 5,
	},
};

/** Custom `invalidMessage` — used by `JsonFallbackControl`. */
export const CustomInvalidMessage: Story = {
	render: (args) => <Controlled {...args} />,
	args: {
		label: "Field settings",
		value: {},
		invalidMessage: "Field settings must be a JSON object.",
		rows: 6,
	},
};
