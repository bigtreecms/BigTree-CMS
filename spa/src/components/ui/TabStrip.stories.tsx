import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";
import { FileText, Search, Share2 } from "lucide-react";

import { TabStrip } from "./TabStrip";

/**
 * Controlled horizontal tab strip. The host owns the active value and renders
 * the panel below the strip (list-only mode) — unlike `TabbedEditor`, which
 * renders its own panels. Supports an optional `trailing` slot and arrow-key
 * roving focus.
 */
const tabs = [
	{ value: "properties", label: "Properties", icon: <FileText size={14} /> },
	{ value: "seo", label: "SEO", icon: <Search size={14} /> },
	{ value: "sharing", label: "Sharing", icon: <Share2 size={14} /> },
];

const meta = {
	title: "UI/TabStrip",
	component: TabStrip,
	tags: ["autodocs"],
	args: { tabs, value: "properties", onChange: () => {} },
	render: (args) => {
		const [value, setValue] = useState(args.value);

		return (
			<div>
				<TabStrip {...args} value={value} onChange={setValue} />
				<p className="pt-4 text-[13px] text-text-2">
					Panel for <strong>{value}</strong> — the host renders this.
				</p>
			</div>
		);
	},
} satisfies Meta<typeof TabStrip>;

export default meta;
type Story = StoryObj<typeof meta>;

/** List-only mode: the host owns the panel rendered beneath the strip. */
export const Default: Story = {};

/** A `trailing` slot pins content (here a search box) to the far right. */
export const WithTrailing: Story = {
	args: {
		trailing: (
			<input
				className="rounded-md border border-border bg-surface px-2 py-1 text-[12.5px] outline-none"
				placeholder="Find a link…"
				type="search"
			/>
		),
	},
};

/** A disabled tab can't be selected and is skipped by arrow-key navigation. */
export const WithDisabledTab: Story = {
	args: {
		tabs: tabs.map((t) => (t.value === "sharing" ? { ...t, disabled: true } : t)),
	},
};
