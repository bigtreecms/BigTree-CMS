import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { Chip } from "./Chip";

/**
 * Toggleable filter pill (e.g. status filters above a list). `active` drives the
 * accent-soft styling. A removable tag is a different control — Chip is a
 * single-toggle filter.
 */
const meta = {
	title: "UI/Chip",
	component: Chip,
	tags: ["autodocs"],
	args: { active: false, children: "Published", onClick: () => {} },
	render: (args) => {
		const [active, setActive] = useState(args.active);

		return (
			<Chip {...args} active={active} onClick={() => setActive((a) => !a)}>
				{args.children}
			</Chip>
		);
	},
} satisfies Meta<typeof Chip>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Inactive: Story = {};

export const Active: Story = {
	args: { active: true },
};

/** A typical filter row — click to toggle each independently. */
export const FilterRow: Story = {
	render: () => {
		const [selected, setSelected] = useState<string[]>(["published"]);
		const filters = ["All", "Published", "Draft", "Archived"];

		const toggle = (f: string) =>
			setSelected((cur) => (cur.includes(f) ? cur.filter((x) => x !== f) : [...cur, f]));

		return (
			<div className="flex flex-wrap gap-2">
				{filters.map((f) => (
					<Chip
						active={selected.includes(f.toLowerCase())}
						key={f}
						onClick={() => toggle(f.toLowerCase())}
					>
						{f}
					</Chip>
				))}
			</div>
		);
	},
};
