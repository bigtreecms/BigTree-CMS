import type { Meta, StoryObj } from "@storybook/react-vite";

import { DescriptionList } from "./DescriptionList";

/**
 * Two-column key/value detail grid for "From / To / Date"-style metadata
 * blocks. `boxed` adds the `surface-2` card framing; `labelWidth` tunes the
 * term column; per-row `valueClassName` handles `font-mono` / `truncate`.
 */
const meta = {
	title: "UI/DescriptionList",
	component: DescriptionList,
	tags: ["autodocs"],
	args: {
		labelWidth: 120,
		items: [
			{ label: "From", value: "Alex Chen" },
			{ label: "To", value: "Priya Natarajan, Sam Okafor" },
			{ label: "Date", value: "Jun 22, 2026 · 9:41 AM" },
		],
	},
} satisfies Meta<typeof DescriptionList>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Bare in-flow grid (no card framing). */
export const Default: Story = {};

/** The `surface-2` card treatment used in detail panels. */
export const Boxed: Story = {
	args: { boxed: true },
};

/** Per-row `valueClassName` for monospace IDs and truncated values. */
export const Mixed: Story = {
	args: {
		boxed: true,
		labelWidth: 110,
		items: [
			{ label: "ID", value: "com.example.gallery", valueClassName: "font-mono" },
			{ label: "Title", value: "Photo Gallery" },
			{
				label: "Path",
				value: "/very/long/storage/path/that/should/truncate/cleanly.jpg",
				valueClassName: "truncate",
			},
			{ label: "Components", value: "4 selected" },
		],
	},
};
