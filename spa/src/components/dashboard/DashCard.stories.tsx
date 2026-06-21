import type { Meta, StoryObj } from "@storybook/react-vite";
import { Activity, ChevronRight, Mail } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { InlineEmpty } from "@/components/ui/InlineEmpty";
import { DashCard } from "./DashCard";

/**
 * The dashboard section-card chrome: an accent-tinted icon badge, an uppercase
 * title, an optional `sub` note and right-aligned `action`, over a padded body.
 * Used by every card on the dashboard (`UnreadMessagesCard`, `TrafficCard`, …).
 */
const meta = {
	title: "Dashboard/DashCard",
	component: DashCard,
	tags: ["autodocs"],
	args: {
		icon: Activity,
		title: "Section title",
		children: <p className="text-[13px] text-text-2">Card body content.</p>,
	},
} satisfies Meta<typeof DashCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** With a `sub` note and a right-aligned `action` button — the typical usage. */
export const WithSubAndAction: Story = {
	args: {
		icon: Mail,
		title: "Unread messages",
		sub: "3 unread",
		action: (
			<Button variant="secondary" size="sm">
				View all messages
				<ChevronRight size={11} />
			</Button>
		),
	},
};

/** Body holding an empty-state placeholder. */
export const EmptyBody: Story = {
	args: {
		icon: Mail,
		title: "Unread messages",
		sub: "All caught up",
		children: <InlineEmpty icon={Mail}>No unread messages</InlineEmpty>,
	},
};
