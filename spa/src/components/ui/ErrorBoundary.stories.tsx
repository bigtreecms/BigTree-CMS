import type { Meta, StoryObj } from "@storybook/react-vite";

import { ErrorBoundary } from "./ErrorBoundary";

/**
 * Catches render-time crashes in the active route so a thrown component doesn't
 * blank the whole app. The `Crashed` story mounts a child that throws on render
 * to show the fallback card (message + "Try again"); `Healthy` renders its
 * children untouched.
 */
const Boom = (): never => {
	throw new Error("Simulated render crash — a child component threw.");
};

const meta = {
	title: "UI/ErrorBoundary",
	component: ErrorBoundary,
	tags: ["autodocs"],
} satisfies Meta<typeof ErrorBoundary>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Children render normally when nothing throws. */
export const Healthy: Story = {
	args: {
		children: (
			<div className="rounded-md border border-border bg-surface p-6 text-[13px] text-text-2">
				This screen rendered without incident.
			</div>
		),
	},
};

/** A child throws on render — the boundary catches it and shows the fallback. */
export const Crashed: Story = {
	args: {
		children: <Boom />,
	},
};
