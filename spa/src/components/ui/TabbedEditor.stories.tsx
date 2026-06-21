import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";
import { FileText, Search, Share2 } from "lucide-react";

import { TabbedEditor } from "./TabbedEditor";

/**
 * Radix Tabs scaffold for multi-tab edit screens (Page edit, Module designer).
 * The host controls the active tab so it can be mirrored in the URL. Overflowing
 * tabs scroll horizontally.
 */
const tabs = [
	{
		value: "properties",
		label: "Properties",
		icon: <FileText size={14} />,
		content: <p className="text-[13px] text-text-2">Page properties form.</p>,
	},
	{
		value: "seo",
		label: "SEO",
		icon: <Search size={14} />,
		content: <p className="text-[13px] text-text-2">SEO metadata form.</p>,
	},
	{
		value: "sharing",
		label: "Sharing",
		icon: <Share2 size={14} />,
		content: <p className="text-[13px] text-text-2">Open Graph + social sharing.</p>,
	},
];

const meta = {
	title: "UI/TabbedEditor",
	component: TabbedEditor,
	tags: ["autodocs"],
	args: { tabs, value: "properties", onChange: () => {} },
	render: (args) => {
		const [value, setValue] = useState(args.value);

		return <TabbedEditor {...args} value={value} onChange={setValue} />;
	},
} satisfies Meta<typeof TabbedEditor>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** A disabled tab can't be selected. */
export const WithDisabledTab: Story = {
	args: {
		tabs: tabs.map((t) => (t.value === "sharing" ? { ...t, disabled: true } : t)),
	},
};
