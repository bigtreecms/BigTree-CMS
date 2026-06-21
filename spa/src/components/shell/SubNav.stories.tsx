import type { Meta, StoryObj } from "@storybook/react-vite";
import { FileText, Image, Settings, Sliders, Users } from "lucide-react";

import { SubNav } from "./SubNav";

/**
 * Route-driven **section sub-navigation** (`shell/SubNav`) — the SPA equivalent
 * of the legacy `<nav id="sub_nav">`. Renders `NavLink`s with active
 * highlighting, supports `external` links (classic-admin actions), and collapses
 * items that don't fit into a trailing "More" dropdown. Contrast with the
 * controlled `ui/SubNav` pill. Stories run inside a `MemoryRouter` (preview
 * decorator).
 */
const meta = {
	title: "Shell/SubNav",
	component: SubNav,
	tags: ["autodocs"],
	args: {
		items: [
			{ label: "Pages", to: "/pages", icon: FileText },
			{ label: "Users", to: "/users", icon: Users },
			{ label: "Media", to: "/media", icon: Image },
			{ label: "Settings", to: "/settings", icon: Settings },
		],
	},
} satisfies Meta<typeof SubNav>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** An `external` item links out to the classic admin and shows the glyph. */
export const WithExternalLink: Story = {
	args: {
		items: [
			{ label: "Pages", to: "/pages", icon: FileText },
			{ label: "Settings", to: "/settings", icon: Settings },
			{
				label: "Classic admin",
				to: "https://example.com/admin",
				icon: Sliders,
				external: true,
			},
		],
	},
};

/**
 * Many items in a narrow viewport collapse into the "More" dropdown (hover or
 * focus the trigger to open). Resize the canvas to watch items move in and out.
 */
export const WithOverflow: Story = {
	args: {
		items: [
			{ label: "Pages", to: "/pages", icon: FileText },
			{ label: "Users", to: "/users", icon: Users },
			{ label: "Media", to: "/media", icon: Image },
			{ label: "Settings", to: "/settings", icon: Settings },
			{ label: "Templates", to: "/templates", icon: FileText },
			{ label: "Modules", to: "/modules", icon: Sliders },
			{ label: "Feeds", to: "/feeds", icon: FileText },
			{ label: "Callouts", to: "/callouts", icon: Image },
		],
	},
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 480 }}>
				<Story />
			</div>
		),
	],
};
