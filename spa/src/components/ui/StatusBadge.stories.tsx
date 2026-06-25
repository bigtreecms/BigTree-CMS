import type { Meta, StoryObj } from "@storybook/react-vite";

import { StatusBadge } from "./StatusBadge";

/**
 * The single home for the status → tone → color mapping. Renders a domain status
 * as a filled pill (`badge`) or an inline uppercase colored label (`text`). Each
 * caller supplies a thin status → { tone, label } map.
 */
const meta = {
	title: "UI/StatusBadge",
	component: StatusBadge,
	tags: ["autodocs"],
	args: { tone: "success", label: "Published" },
} satisfies Meta<typeof StatusBadge>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** The classic status-pill treatment: a leading colored dot. */
export const WithDot: Story = {
	args: { dot: true },
};

/** The inline uppercase colored label used by the list-view "Status" cell. */
export const Text: Story = {
	args: { variant: "text", tone: "warn", label: "Pending" },
};

/** A few states side by side, in both variants. */
export const States: Story = {
	render: () => (
		<div className="space-y-3">
			<div className="flex flex-wrap items-center gap-2">
				<StatusBadge tone="success" label="Published" dot />
				<StatusBadge tone="warn" label="Pending" dot />
				<StatusBadge tone="info" label="Scheduled" dot />
				<StatusBadge tone="neutral" label="Archived" dot />
			</div>
			<div className="flex flex-wrap items-center gap-4">
				<StatusBadge variant="text" tone="success" label="Published" />
				<StatusBadge variant="text" tone="warn" label="Changed" />
				<StatusBadge variant="text" tone="neutral" label="Inactive" />
			</div>
		</div>
	),
};
