import type { Meta, StoryObj } from "@storybook/react-vite";
import { Key } from "lucide-react";

import { Badge, type BadgeTone } from "./Badge";

/**
 * Static status / label pill. Unlike `Chip` (a toggleable filter), a Badge is
 * non-interactive — it just communicates state. Tones map to design tokens so
 * badges stay legible in light + dark mode.
 */
const meta = {
	title: "UI/Badge",
	component: Badge,
	tags: ["autodocs"],
	args: { children: "Published", tone: "neutral" },
} satisfies Meta<typeof Badge>;

export default meta;
type Story = StoryObj<typeof meta>;

const TONES: BadgeTone[] = ["neutral", "accent", "success", "warn", "danger", "info"];

export const Default: Story = {};

/** Every tone, side by side. */
export const Tones: Story = {
	render: () => (
		<div className="flex flex-wrap gap-2">
			{TONES.map((tone) => (
				<Badge key={tone} tone={tone}>
					{tone}
				</Badge>
			))}
		</div>
	),
};

/** The classic status-pill treatment: a leading colored dot. */
export const WithDot: Story = {
	render: () => (
		<div className="flex flex-wrap gap-2">
			{TONES.map((tone) => (
				<Badge key={tone} tone={tone} dot>
					{tone}
				</Badge>
			))}
		</div>
	),
};

/** A leading icon node (caller controls its size). */
export const WithIcon: Story = {
	args: { tone: "accent", icon: <Key size={9} />, children: "Developer" },
};

/** Subtle neutral pill with an outline. */
export const Bordered: Story = {
	args: { bordered: true, children: "Normal User" },
};

/** Uppercase treatment, e.g. the "Pending" field badge. */
export const Uppercase: Story = {
	args: { tone: "warn", dot: true, uppercase: true, children: "Pending" },
};
