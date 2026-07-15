import type { Meta, StoryObj } from "@storybook/react-vite";
import { ExternalLink, LayoutGrid } from "lucide-react";

import { SubNavItemContent } from "./SubNavItemContent";

/**
 * The inner content shared by both SubNav variants — an optional leading icon,
 * the label, and an optional trailing adornment, in that fixed order. The
 * wrapping button/anchor and its variant classes live with each SubNav; this
 * owns only the ordering. Rendered here inside a sample pill so the fragment is
 * visible.
 */
const meta = {
	title: "UI/SubNavItemContent",
	component: SubNavItemContent,
	tags: ["autodocs"],
	args: {
		label: "Overview",
	},
	decorators: [
		(Story) => (
			<span className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[13px] font-medium text-text">
				<Story />
			</span>
		),
	],
} satisfies Meta<typeof SubNavItemContent>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Label only. */
export const LabelOnly: Story = {};

/** With a leading icon. */
export const WithIcon: Story = {
	args: {
		icon: <LayoutGrid size={13} />,
	},
};

/** With a leading icon and a trailing external-link glyph. */
export const WithTrailing: Story = {
	args: {
		icon: <LayoutGrid size={13} />,
		label: "Live site",
		trailing: <ExternalLink className="text-text-3" size={12} />,
	},
};
