import type { Meta, StoryObj } from "@storybook/react-vite";
import { useState } from "react";

import { DisclosureToggle } from "./DisclosureToggle";

/**
 * The shared chevron expand/collapse toggle. Owns the chevron swap and
 * `aria-expanded`; the host owns the collapsed content. Container styling
 * (gap, padding, width, hover) is supplied via `className`.
 */
const meta = {
	title: "UI/DisclosureToggle",
	component: DisclosureToggle,
	tags: ["autodocs"],
	args: {
		open: false,
		onToggle: () => {},
		label: "Host API reference",
		className: "gap-1.5 text-[12px] font-medium text-text-2",
	},
} satisfies Meta<typeof DisclosureToggle>;

export default meta;
type Story = StoryObj<typeof meta>;

const Interactive = ({ size }: { size?: number }) => {
	const [open, setOpen] = useState(false);

	return (
		<DisclosureToggle
			className="gap-1.5 text-[12px] font-medium text-text-2"
			label="Host API reference"
			open={open}
			size={size}
			onToggle={() => setOpen((v) => !v)}
		/>
	);
};

/** Closed by default — click to expand. */
export const Closed: Story = {
	render: () => <Interactive />,
};

/** Open state — the chevron points down. */
export const Open: Story = {
	args: { open: true },
};

/** A trailing count rendered after the label via `children`. */
export const WithTrailingBadge: Story = {
	args: {
		open: true,
		className: "gap-2",
		label: (
			<h3 className="text-[11px] font-semibold uppercase tracking-[0.08em] text-text">
				Published
			</h3>
		),
		children: <span className="text-[11px] tabular-nums text-text-3">12</span>,
	},
};
