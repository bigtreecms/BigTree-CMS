import type { Meta, StoryObj } from "@storybook/react-vite";

import { MonoText } from "./MonoText";

/**
 * The muted monospace token for an ID, hash, file path, or route string — the
 * single source of truth for the `font-mono text-[11px] text-text-3` treatment
 * sprinkled beneath labels across the developer screens.
 */
const meta = {
	title: "UI/Typography/MonoText",
	component: MonoText,
	tags: ["autodocs"],
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 240 }}>
				<Story />
			</div>
		),
	],
} satisfies Meta<typeof MonoText>;

export default meta;
type Story = StoryObj<typeof meta>;

/** A plain ID token. */
export const Default: Story = {
	args: {
		children: "550e8400-e29b-41d4-a716-446655440000",
	},
};

/** Truncating an over-long route path with an ellipsis (the default). */
export const Truncated: Story = {
	args: {
		children: "/admin/developer/module-designer/very/long/route/path",
	},
};
