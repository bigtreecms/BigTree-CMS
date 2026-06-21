import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { SearchInput } from "./SearchInput";

/**
 * Toolbar search box — leading icon, input, and a clear button that appears once
 * there's a query. The single source of truth for the list-filter pattern across
 * the admin (Tags, Files, Users, Modules, …).
 */
const meta = {
	title: "UI/Form/SearchInput",
	component: SearchInput,
	tags: ["autodocs"],
	// `value`/`onChange` are supplied by the stateful `render` below; the no-op
	// defaults here just satisfy Storybook's required-args typing.
	args: { value: "", onChange: () => {}, placeholder: "Search…", "aria-label": "Search" },
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 320 }}>
				<Story />
			</div>
		),
	],
	render: (args) => {
		const [value, setValue] = useState(args.value ?? "");

		return <SearchInput {...args} value={value} onChange={setValue} />;
	},
} satisfies Meta<typeof SearchInput>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Empty: Story = {};

/** With a query, the trailing clear button appears. */
export const WithQuery: Story = {
	args: { value: "homepage" },
};
