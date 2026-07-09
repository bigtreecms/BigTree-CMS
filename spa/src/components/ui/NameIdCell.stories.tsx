import type { Meta, StoryObj } from "@storybook/react-vite";

import { NameIdCell } from "./NameIdCell";

/**
 * The two-line name-over-mono-id table cell shared by the developer list pages.
 * A bold, truncating display name sits above a muted monospace ID.
 */
const meta = {
	title: "UI/NameIdCell",
	component: NameIdCell,
	tags: ["autodocs"],
	args: {
		name: "Featured Articles",
		id: "featured-articles",
	},
} satisfies Meta<typeof NameIdCell>;

export default meta;
type Story = StoryObj<typeof meta>;

/** The default cell as it appears in a list-page "Name" column. */
export const Default: Story = {};

/** A long name and ID both clip with an ellipsis inside the constrained cell. */
export const Truncated: Story = {
	render: (args) => (
		<div className="w-56">
			<NameIdCell {...args} />
		</div>
	),
	args: {
		name: "A very long resource name that will not fit in the column",
		id: "a-very-long-resource-identifier-that-overflows",
	},
};
