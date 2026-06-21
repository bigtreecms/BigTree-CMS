import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";
import { List, Settings, Users } from "lucide-react";

import { SubNav } from "./SubNav";
import { SubNav as ShellSubNav } from "../shell/SubNav";

/**
 * Controlled **segmented pill** sub-nav (`ui/SubNav`). State is the source of
 * truth — the host owns `value` and wires `onChange` to either `setState` or
 * `navigate(item.to)`. Contrast with the route-driven `shell/SubNav` (see the
 * "Vs Shell SubNav" story) — the two are mechanically different on purpose
 * (finding F4 in the style-consistency plan keeps both).
 */
const items = [
	{ value: "all", label: "All", icon: <List size={13} /> },
	{ value: "users", label: "Users", icon: <Users size={13} /> },
	{ value: "settings", label: "Settings", icon: <Settings size={13} /> },
];

const meta = {
	title: "UI/SubNav",
	component: SubNav,
	tags: ["autodocs"],
	args: { items, value: "all", onChange: () => {} },
	render: (args) => {
		const [value, setValue] = useState(args.value);

		return <SubNav {...args} value={value} onChange={setValue} />;
	},
} satisfies Meta<typeof SubNav>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithoutIcons: Story = {
	args: {
		items: [
			{ value: "all", label: "All" },
			{ value: "users", label: "Users" },
			{ value: "settings", label: "Settings" },
		],
	},
};

/**
 * The two SubNavs side by side. `ui/SubNav` (top) is a controlled pill; the
 * route-driven `shell/SubNav` (bottom) renders `NavLink`s, supports external
 * links, and collapses overflow into a "More" menu.
 *
 * **F4 decision:** the two are mechanically different (controlled state vs
 * routed `NavLink`) so they keep separate APIs — but both now render their item
 * content through the shared `SubNavItemContent` atom (`ui/SubNavItemContent`),
 * the one visual layer they share, so icon/label ordering can't drift.
 */
export const VsShellSubNav: Story = {
	render: () => {
		const [value, setValue] = useState("all");

		return (
			<div className="flex flex-col gap-8">
				<div>
					<div className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-text-3">
						ui/SubNav — controlled pill
					</div>
					<SubNav items={items} value={value} onChange={setValue} />
				</div>

				<div>
					<div className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-text-3">
						shell/SubNav — routed NavLinks
					</div>
					<ShellSubNav
						items={[
							{ label: "Overview", to: "/overview", icon: List },
							{ label: "Users", to: "/users", icon: Users },
							{ label: "Settings", to: "/settings", icon: Settings },
						]}
					/>
				</div>
			</div>
		);
	},
};
