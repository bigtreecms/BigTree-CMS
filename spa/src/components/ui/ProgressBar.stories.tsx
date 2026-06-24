import type { Meta, StoryObj } from "@storybook/react-vite";

import { ProgressBar } from "./ProgressBar";

/**
 * Determinate progress meter — a `bg-surface-2` track with a token-colored
 * fill. The shared source of truth for upload and scan progress bars. Pass a
 * width via `className` (the track has none of its own).
 */
const meta = {
	title: "UI/ProgressBar",
	component: ProgressBar,
	tags: ["autodocs"],
	args: { value: 60, size: "sm", tone: "accent", className: "w-64" },
	argTypes: {
		value: { control: { type: "range", min: 0, max: 100, step: 1 } },
		size: { control: "inline-radio", options: ["sm", "md"] },
		tone: { control: "inline-radio", options: ["accent", "success"] },
	},
} satisfies Meta<typeof ProgressBar>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Playground: Story = {};

/** The two heights — `sm` (inline upload meters) and `md` (block scan bars). */
export const Sizes: Story = {
	render: () => (
		<div className="flex w-64 flex-col gap-3">
			<ProgressBar value={45} size="sm" className="w-full" />
			<ProgressBar value={45} size="md" className="w-full" />
		</div>
	),
};

/** A `success` tone marks a finished bar (e.g. an integrity scan that completed). */
export const Complete: Story = {
	args: { value: 100, tone: "success", size: "md", className: "w-64" },
};

/** Inline meter (`w-24`) next to a percentage label, as used by the upload fields. */
export const InlineMeter: Story = {
	render: () => (
		<span className="inline-flex items-center gap-2 text-[12px] text-text-3">
			<ProgressBar value={72} className="w-24" />
			72%
		</span>
	),
};
