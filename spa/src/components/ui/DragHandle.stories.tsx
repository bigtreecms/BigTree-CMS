import type { Meta, StoryObj } from "@storybook/react-vite";

import { DragHandle } from "./DragHandle";

/**
 * The shared drag-reorder grip. Pairs with `useDragReorder` for the logic —
 * this is just the consistent visual handle that sits at the start of a
 * sortable row.
 */
const meta = {
	title: "UI/DragHandle",
	component: DragHandle,
	tags: ["autodocs"],
	args: { enabled: true },
	argTypes: { enabled: { control: "boolean" } },
} satisfies Meta<typeof DragHandle>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** Disabled — dimmed, no grab cursor (e.g. reordering not allowed for the current filter). */
export const Disabled: Story = {
	args: { enabled: false, title: "Reordering disabled" },
};

/** In context: a grip at the start of a row. */
export const InRow: Story = {
	render: () => (
		<div className="flex w-72 items-center gap-2 rounded-md border border-border bg-surface px-2 py-1.5 text-[13px]">
			<DragHandle />
			<span className="text-text-2">Reorderable row</span>
		</div>
	),
};
