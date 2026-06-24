import type { Meta, StoryObj } from "@storybook/react-vite";
import { Settings } from "lucide-react";

import { Badge } from "./Badge";
import { IconButton } from "./IconButton";
import { SectionLabel } from "./SectionLabel";

/**
 * The small uppercase "overline" that titles a settings group or panel section.
 * The single source of truth for `font-semibold uppercase tracking-[0.06em]
 * text-text-3`. Keep the surrounding margin/padding in `className` — the base has none.
 */
const meta = {
	title: "UI/SectionLabel",
	component: SectionLabel,
	tags: ["autodocs"],
	args: { children: "Permissions", size: "md" },
	argTypes: {
		size: { control: "inline-radio", options: ["sm", "md"] },
	},
} satisfies Meta<typeof SectionLabel>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Playground: Story = {};

/** The two sizes — `md` (12px, default) and `sm` (11px, denser panels). */
export const Sizes: Story = {
	render: () => (
		<div className="flex flex-col gap-3">
			<SectionLabel size="md">Details</SectionLabel>
			<SectionLabel size="sm">Details</SectionLabel>
		</div>
	),
};

/** A leading icon switches the box to a flex row. */
export const WithIcon: Story = {
	args: { icon: <Settings size={13} />, children: "Settings" },
};

/** Trailing `actions` (a count, a toggle) are pushed to the right edge. */
export const WithActions: Story = {
	render: () => (
		<div className="w-72">
			<SectionLabel actions={<Badge tone="accent">3</Badge>}>Pending changes</SectionLabel>
		</div>
	),
};

/** An action button can live in the `actions` slot for a section toolbar. */
export const WithActionButton: Story = {
	render: () => (
		<div className="w-72">
			<SectionLabel
				actions={
					<IconButton label="Configure">
						<Settings size={14} />
					</IconButton>
				}
			>
				Integrations
			</SectionLabel>
		</div>
	),
};
