import { useState } from "react";
import { ChevronsUpDown } from "lucide-react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { Popover } from "./Popover";

/**
 * Controlled trigger + floating panel. Owns the `relative` container, the
 * close-on-outside-click (`useOnClickOutside`), and the shared panel surface;
 * the trigger node wires its own `onClick` / `aria-expanded`. `panelClassName`
 * carries the per-site width / anchor / max-height / overflow.
 */
const meta = {
	title: "UI/Popover",
	component: Popover,
	tags: ["autodocs"],
	args: { open: false, onOpenChange: () => {}, trigger: null, children: null },
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 320 }}>
				<Story />
			</div>
		),
	],
} satisfies Meta<typeof Popover>;

export default meta;
type Story = StoryObj<typeof meta>;

const Trigger = ({ open, onToggle }: { open: boolean; onToggle: () => void }) => (
	<button
		type="button"
		aria-haspopup="dialog"
		aria-expanded={open}
		onClick={onToggle}
		className="flex w-full items-center justify-between rounded-md border border-border bg-surface py-1.5 pl-3 pr-2 text-left text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
	>
		<span>{open ? "Close panel" : "Open panel"}</span>
		<ChevronsUpDown size={13} className="text-text-3" />
	</button>
);

/** Default stretch panel: matches the trigger width. */
export const Default: Story = {
	render: () => {
		const [open, setOpen] = useState(false);

		return (
			<Popover
				open={open}
				onOpenChange={setOpen}
				className="w-full"
				panelClassName="w-full overflow-hidden"
				trigger={<Trigger open={open} onToggle={() => setOpen((prev) => !prev)} />}
			>
				<div className="px-3 py-2 text-[13px] text-text-2">
					A floating panel anchored under the trigger. Click outside to dismiss.
				</div>
			</Popover>
		);
	},
};

/** End-aligned, fixed-width panel (the LinkFinder anchor variant). */
export const EndAligned: Story = {
	render: () => {
		const [open, setOpen] = useState(false);

		return (
			<div className="flex justify-end">
				<Popover
					open={open}
					onOpenChange={setOpen}
					panelClassName="right-0 top-full w-[min(320px,90vw)] overflow-hidden"
					trigger={<Trigger open={open} onToggle={() => setOpen((prev) => !prev)} />}
				>
					<div className="px-3 py-2 text-[13px] text-text-2">
						Anchored to the right edge with a capped width.
					</div>
				</Popover>
			</div>
		);
	},
};

/** Scrolling list inside a height-capped panel. */
export const ScrollingList: Story = {
	render: () => {
		const [open, setOpen] = useState(false);

		return (
			<Popover
				open={open}
				onOpenChange={setOpen}
				className="w-full"
				panelClassName="w-full max-h-48 overflow-y-auto"
				trigger={<Trigger open={open} onToggle={() => setOpen((prev) => !prev)} />}
			>
				<ul className="py-1">
					{Array.from({ length: 20 }, (_, i) => (
						<li key={i} className="px-3 py-1.5 text-[13px] text-text-2 hover:bg-hover">
							Option {i + 1}
						</li>
					))}
				</ul>
			</Popover>
		);
	},
};
