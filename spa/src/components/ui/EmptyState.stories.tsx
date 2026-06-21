import type { Meta, StoryObj } from "@storybook/react-vite";

import { EmptyState } from "./EmptyState";

/**
 * The centered placeholder shown in place of an empty list or section. Solid by
 * default; `dashed` gives the "nothing here yet" drop-zone treatment.
 */
const meta = {
	title: "UI/EmptyState",
	component: EmptyState,
	tags: ["autodocs"],
	args: { children: "No modules available." },
} satisfies Meta<typeof EmptyState>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Solid: Story = {};

export const Dashed: Story = {
	args: { dashed: true, children: "No modules match your search." },
};
