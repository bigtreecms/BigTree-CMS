import type { Meta, StoryObj } from "@storybook/react-vite";
import { Mail } from "lucide-react";

import { InlineEmpty } from "./InlineEmpty";

/**
 * The compact dashed placeholder used inside cards and sections — the dense
 * sibling of `EmptyState`. Use it for empty lists, "not configured yet" notes,
 * and dashboard placeholders.
 */
const meta = {
	title: "UI/InlineEmpty",
	component: InlineEmpty,
	tags: ["autodocs"],
	args: { children: "No items here yet." },
} satisfies Meta<typeof InlineEmpty>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Note: Story = {
	args: { pad: "md", children: "Google Analytics isn't connected yet." },
};

export const Centered: Story = {
	args: { align: "center", children: "No subpages yet." },
};

export const WithIcon: Story = {
	args: { icon: Mail, children: "No unread messages" },
};

export const Variants: Story = {
	render: (args) => (
		<div className="flex max-w-md flex-col gap-3">
			<InlineEmpty {...args} pad="sm">
				Compact note (pad sm)
			</InlineEmpty>
			<InlineEmpty {...args} pad="md">
				Default note (pad md)
			</InlineEmpty>
			<InlineEmpty {...args} align="center" pad="lg">
				Empty list (center, pad lg)
			</InlineEmpty>
			<InlineEmpty {...args} align="center" pad="xl">
				Taller empty list (center, pad xl)
			</InlineEmpty>
			<InlineEmpty {...args} icon={Mail}>
				With a leading icon
			</InlineEmpty>
		</div>
	),
};
