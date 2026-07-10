import type { Meta, StoryObj } from "@storybook/react-vite";
import { Layers, Layout, Megaphone, Rss, Wrench } from "lucide-react";

import { TileLinkGrid } from "./TileLinkGrid";

/**
 * The grid of icon-tile navigation cards behind the developer index pages
 * (Developer, Configure, Debug). Feed it `{ to, icon, title, description }`
 * items; the grid, hover treatment and card chrome are fixed so the three
 * indexes stay in lockstep.
 */
const meta = {
	title: "UI/TileLinkGrid",
	component: TileLinkGrid,
	tags: ["autodocs"],
	args: {
		items: [
			{
				to: "/developer/templates",
				icon: <Layout size={16} />,
				title: "Templates",
				description: "Page templates and their content resources.",
			},
			{
				to: "/developer/modules",
				icon: <Layers size={16} />,
				title: "Modules",
				description: "Module designer — forms, views, reports, embedded forms.",
			},
			{
				to: "/developer/callouts",
				icon: <Megaphone size={16} />,
				title: "Callouts",
				description: "Reusable content blocks the Callouts field draws from.",
			},
			{
				to: "/developer/field-types",
				icon: <Wrench size={16} />,
				title: "Field types",
				description: "Custom form field types (alongside the built-ins).",
			},
			{
				to: "/developer/feeds",
				icon: <Rss size={16} />,
				title: "Feeds",
				description: "Public RSS / JSON / XML endpoints driven from module tables.",
			},
		],
	},
} satisfies Meta<typeof TileLinkGrid>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** One tile — the grid collapses to a single column below `md`. */
export const SingleTile: Story = {
	args: {
		items: [
			{
				to: "/developer/extensions",
				icon: <Layers size={16} />,
				title: "Extensions",
				description: "Build, install, upgrade, and uninstall extension packages.",
			},
		],
	},
};
