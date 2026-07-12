import type { Meta, StoryObj } from "@storybook/react-vite";
import { Database, FileText, Folder, Info } from "lucide-react";

import { IconTile } from "./IconTile";

/**
 * The small square "icon chip" — a centered grid box tinting a leading icon in
 * empty states, wizard steps and list rows. The single source of truth for
 * `grid place-items-center` + a token tint. Keep layout (`shrink-0`, `mx-auto`)
 * in `className`.
 */
const meta = {
	title: "UI/IconTile",
	component: IconTile,
	tags: ["autodocs"],
	args: {
		tone: "accent",
		size: "md",
		radius: "md",
		ringed: false,
		children: <Database size={18} />,
	},
	argTypes: {
		tone: {
			control: "inline-radio",
			options: ["accent", "neutral", "info", "warn", "brand"],
		},
		size: { control: "inline-radio", options: ["xs", "sm", "md", "lg"] },
		radius: { control: "inline-radio", options: ["md", "lg", "full"] },
		ringed: { control: "boolean" },
	},
} satisfies Meta<typeof IconTile>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Playground: Story = {};

/** The token tints, including warn and solid brand. */
export const Tones: Story = {
	render: () => (
		<div className="flex items-center gap-3">
			<IconTile tone="accent">
				<Database size={18} />
			</IconTile>
			<IconTile tone="neutral">
				<FileText size={18} />
			</IconTile>
			<IconTile tone="info" radius="full" size="lg">
				<Info size={18} />
			</IconTile>
			<IconTile tone="warn" size="xs">
				<Info size={14} />
			</IconTile>
			<IconTile tone="brand" size="xs">
				<Database size={14} />
			</IconTile>
		</div>
	),
};

/** The size scale — `xs` (26px), `sm` (size-7), `md` (size-9), `lg` (size-10). */
export const Sizes: Story = {
	render: () => (
		<div className="flex items-center gap-3">
			<IconTile size="xs">
				<Database size={14} />
			</IconTile>
			<IconTile size="sm">
				<Database size={14} />
			</IconTile>
			<IconTile size="md">
				<Database size={18} />
			</IconTile>
			<IconTile size="lg">
				<Database size={20} />
			</IconTile>
		</div>
	),
};

/** A `ring-1 ring-border` outline — the file/folder thumbnail treatment. */
export const Ringed: Story = {
	render: () => (
		<div className="flex items-center gap-3">
			<IconTile tone="neutral" ringed>
				<FileText size={16} />
			</IconTile>
			<IconTile tone="accent" ringed>
				<Folder size={16} />
			</IconTile>
		</div>
	),
};
