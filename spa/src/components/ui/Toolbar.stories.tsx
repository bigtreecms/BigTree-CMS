import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { Toolbar } from "./Toolbar";
import { SearchInput } from "./SearchInput";
import { Pager } from "./Pager";
import { Button } from "./Button";

/**
 * The list/table toolbar row: a left-pinned search box, a flex spacer, then
 * right-aligned controls. Owns the responsive search-width recipe so every list
 * screen's toolbar stays in lockstep.
 */
const meta = {
	title: "UI/Layout/Toolbar",
	component: Toolbar,
	tags: ["autodocs"],
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 720 }}>
				<Story />
			</div>
		),
	],
} satisfies Meta<typeof Toolbar>;

export default meta;
type Story = StoryObj<typeof meta>;

const SearchSlot = () => {
	const [value, setValue] = useState("");

	return (
		<SearchInput aria-label="Search" placeholder="Search…" value={value} onChange={setValue} />
	);
};

/** Search box only — sits left, grows to its max width. */
export const SearchOnly: Story = {
	args: { search: <SearchSlot /> },
};

/** Search left, pager pushed right by the spacer. */
export const SearchAndPager: Story = {
	args: {
		search: <SearchSlot />,
		children: <Pager page={1} totalPages={8} onChange={() => {}} />,
	},
};

/** Search left, a count plus actions on the right. */
export const SearchAndActions: Story = {
	args: {
		search: <SearchSlot />,
		children: (
			<>
				<span className="text-[12px] tabular-nums text-text-3">128 items</span>
				<Button variant="primary">New</Button>
			</>
		),
	},
};

/** No search slot — just right-aligned actions. */
export const ActionsOnly: Story = {
	args: {
		children: <Button variant="primary">New</Button>,
	},
};
