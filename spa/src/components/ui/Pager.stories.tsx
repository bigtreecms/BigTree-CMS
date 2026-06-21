import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { Pager } from "./Pager";

/**
 * Pagination control for list views (Users, Settings, …). Renders nothing when
 * `totalPages <= 1`; collapses long ranges to first/last + a window around the
 * current page with ellipses.
 */
const meta = {
	title: "UI/Pager",
	component: Pager,
	tags: ["autodocs"],
	args: { page: 1, totalPages: 5, onChange: () => {} },
	render: (args) => {
		const [page, setPage] = useState(args.page);

		return <Pager {...args} page={page} onChange={setPage} />;
	},
} satisfies Meta<typeof Pager>;

export default meta;
type Story = StoryObj<typeof meta>;

export const FewPages: Story = {};

/** Long ranges collapse with ellipses around the current page. */
export const ManyPages: Story = {
	args: { page: 8, totalPages: 20 },
};

/** A single page renders nothing. */
export const SinglePage: Story = {
	args: { totalPages: 1 },
};
