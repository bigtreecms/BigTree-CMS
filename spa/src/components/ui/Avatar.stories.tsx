import type { Meta, StoryObj } from "@storybook/react-vite";

import { Avatar } from "./Avatar";

/**
 * Circular avatar — initials on a deterministic pastel disc derived from the
 * name (or email), with an optional Gravatar overlay. The disc color is
 * theme-independent, so it reads in both light and dark mode. Decorative
 * (`aria-hidden`): the accessible identity is the adjacent name text.
 */
const meta = {
	title: "UI/Avatar",
	component: Avatar,
	tags: ["autodocs"],
	args: { name: "Alex Chen", size: 28 },
} satisfies Meta<typeof Avatar>;

export default meta;
type Story = StoryObj<typeof meta>;

const NAMES = ["Alex Chen", "Priya Natarajan", "Sam Okafor", "Wei Zhang", "Jordan Lee"];

export const Default: Story = {};

/** Deterministic per-name colors, side by side. */
export const Palette: Story = {
	render: () => (
		<div className="flex flex-wrap items-center gap-3">
			{NAMES.map((name) => (
				<Avatar key={name} name={name} size={36} />
			))}
		</div>
	),
};

/** A range of sizes — font scales with the disc. */
export const Sizes: Story = {
	render: () => (
		<div className="flex flex-wrap items-center gap-3">
			{[20, 24, 28, 40, 48, 56].map((size) => (
				<Avatar key={size} name="Alex Chen" size={size} />
			))}
		</div>
	),
};

/** No name — initials fall back to the email's first letter. */
export const EmailOnly: Story = {
	args: { name: undefined, email: "support@example.com", size: 36 },
};

/** Nothing identifying — a neutral "?" disc. */
export const Unknown: Story = {
	args: { name: undefined, email: undefined, size: 36 },
};

/**
 * Gravatar overlay. The disc shows immediately and is replaced by the account's
 * Gravatar when one exists; emails without a Gravatar keep the initials disc.
 */
export const WithGravatar: Story = {
	args: { name: "Octocat", email: "octocat@github.com", gravatar: true, size: 56 },
};
