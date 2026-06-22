import type { Meta, StoryObj } from "@storybook/react-vite";

import { Loading } from "./Loading";

/**
 * The shared loading indicator — a muted spinner plus label. `inline` for
 * inside cards/lists, `block` for a centered in-flow region, `card` for a
 * page-level bordered placeholder.
 */
const meta = {
	title: "UI/Loading",
	component: Loading,
	tags: ["autodocs"],
	args: { label: "Loading…", variant: "inline" },
} satisfies Meta<typeof Loading>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Inline: Story = {};

export const Block: Story = {
	args: { variant: "block", label: "Loading modules…" },
};

export const Card: Story = {
	args: { variant: "card" },
};

/** Text only — no spinner. */
export const NoSpinner: Story = {
	args: { hideSpinner: true },
};
