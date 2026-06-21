import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { Combobox, type ComboboxOption } from "./Combobox";

/**
 * Searchable single-select. A button trigger opens a popover with a filter input
 * and a keyboard-navigable list. Two modes: **local** (pass `options`, it filters
 * here) or **async** (also pass `onSearchChange` and the parent supplies the
 * result set). The selected option is passed whole so the trigger can show its
 * label even when it's not in the current results.
 */
const people: ComboboxOption<string>[] = [
	{ value: "ada", label: "Ada Lovelace", sublabel: "ada@example.com" },
	{ value: "alan", label: "Alan Turing", sublabel: "alan@example.com" },
	{ value: "grace", label: "Grace Hopper", sublabel: "grace@example.com" },
	{ value: "katherine", label: "Katherine Johnson", sublabel: "katherine@example.com" },
];

// Pin the generic so Storybook can type `args`.
const StringCombobox = Combobox<string>;

const meta = {
	title: "UI/Combobox",
	component: StringCombobox,
	tags: ["autodocs"],
	args: { value: null, onChange: () => {}, options: people, placeholder: "Select a user…" },
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 320 }}>
				<Story />
			</div>
		),
	],
	render: (args) => {
		const [value, setValue] = useState<ComboboxOption<string> | null>(args.value);

		return <StringCombobox {...args} value={value} onChange={setValue} />;
	},
} satisfies Meta<typeof StringCombobox>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithSelection: Story = {
	args: { value: people[1] ?? null },
};

export const Loading: Story = {
	args: { isLoading: true },
};

export const Disabled: Story = {
	args: { value: people[0] ?? null, disabled: true },
};
