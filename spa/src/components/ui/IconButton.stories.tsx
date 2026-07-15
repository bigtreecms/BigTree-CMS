import type { Meta, StoryObj } from "@storybook/react-vite";
import { Check, ChevronDown, ExternalLink, Pencil, Trash, X } from "lucide-react";

import { IconButton } from "./IconButton";

/**
 * `IconButton` is the shared icon-only affordance — the small square button
 * that sits in table rows, toolbars, and dense editor lists. Tones map to the
 * hover color: `default` (neutral), `danger` (remove/delete), `accent`. It
 * always carries an accessible `label`.
 */
const meta = {
	title: "UI/IconButton",
	component: IconButton,
	tags: ["autodocs"],
	args: {
		label: "Remove",
		tone: "danger",
		children: <Trash size={13} />,
	},
	argTypes: {
		tone: {
			control: "inline-radio",
			options: ["default", "danger", "accent", "success"],
		},
		onClick: { action: "clicked" },
	},
} satisfies Meta<typeof IconButton>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Playground: Story = {};

/** The three tones, side by side. */
export const Tones: Story = {
	render: () => (
		<div className="flex items-center gap-2">
			<IconButton label="Edit" title="Edit" tone="default">
				<Pencil size={13} />
			</IconButton>
			<IconButton label="Remove" title="Remove" tone="danger">
				<Trash size={13} />
			</IconButton>
			<IconButton label="Expand" title="Expand" tone="accent">
				<ChevronDown size={13} />
			</IconButton>
			<IconButton label="Approve" title="Approve" tone="success">
				<Check size={13} />
			</IconButton>
		</div>
	),
};

/**
 * `size="sm"` (`p-0.5`) is the denser hit target for tree expand/collapse
 * chevrons and inline clear toggles; `md` (default `p-1`) is the toolbar/row size.
 */
export const Small: Story = {
	render: () => (
		<div className="flex items-center gap-2">
			<IconButton label="Expand" size="sm" title="Expand" tone="default">
				<ChevronDown size={13} />
			</IconButton>
			<IconButton label="Clear" size="sm" title="Clear" tone="default">
				<X size={13} />
			</IconButton>
			<IconButton label="Remove" size="sm" title="Remove" tone="danger">
				<Trash size={13} />
			</IconButton>
		</div>
	),
};

/**
 * `to` renders a router `<Link>` and `href` renders an `<a>` — useful for
 * icon-only row actions that navigate (e.g. an Edit pencil linking to a form).
 * Both keep the same look and the required accessible `label`.
 */
export const AsLink: Story = {
	render: () => (
		<div className="flex items-center gap-2">
			<IconButton label="Edit" title="Edit" to="/pages" tone="default">
				<Pencil size={13} />
			</IconButton>
			<IconButton
				href="https://www.bigtreecms.org"
				label="Open docs"
				target="_blank"
				title="Open docs"
				tone="accent"
			>
				<ExternalLink size={13} />
			</IconButton>
		</div>
	),
};

/** A disabled control dims and drops its hover treatment (always a plain `<button>`). */
export const Disabled: Story = {
	args: { disabled: true },
};

/** `className` carries layout-only tweaks — e.g. an input's clear button pinned to the right edge. */
export const Positioned: Story = {
	render: () => (
		<div className="relative w-56 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] text-text-2">
			Search term
			<IconButton
				className="absolute right-2 top-1/2 -translate-y-1/2"
				label="Clear"
				tone="default"
			>
				<X size={14} />
			</IconButton>
		</div>
	),
};
