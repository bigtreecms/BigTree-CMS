import type { Meta, StoryObj } from "@storybook/react-vite";

import { Card, CardHeader } from "./Card";

/**
 * The standard content panel (`rounded-xl border border-border bg-surface`).
 * Pair a flush `Card` with {@link CardHeader} for a titled card; pass a
 * `padding` size for a simple content box.
 */
const meta = {
	title: "UI/Card",
	component: Card,
	tags: ["autodocs"],
	args: { children: "Card content." },
} satisfies Meta<typeof Card>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
	args: { padding: "md" },
};

/** Padding sizes: `sm` (p-4), `md` (p-5), `lg` (p-6). */
export const Paddings: Story = {
	render: () => (
		<div className="flex flex-col gap-4">
			<Card padding="sm">Small padding (p-4)</Card>
			<Card padding="md">Medium padding (p-5)</Card>
			<Card padding="lg">Large padding (p-6)</Card>
		</div>
	),
};

/** Flush card with a titled header — the canonical "section" surface. */
export const WithHeader: Story = {
	render: () => (
		<Card className="overflow-hidden">
			<CardHeader title="Traffic sources" description="Last 14 days" />
			<div className="p-4 text-[13px] text-text-2">Body content goes here.</div>
		</Card>
	),
};

/** A bespoke header (custom typography / actions) via `children`. */
export const CustomHeader: Story = {
	render: () => (
		<Card className="overflow-hidden">
			<CardHeader className="flex items-center justify-between">
				<h2 className="text-[13px] font-semibold text-text">Pending changes</h2>
				<span className="text-[11px] text-text-3">3 items</span>
			</CardHeader>
			<div className="p-4 text-[13px] text-text-2">Body content goes here.</div>
		</Card>
	),
};
