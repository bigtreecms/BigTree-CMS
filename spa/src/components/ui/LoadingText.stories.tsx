import type { Meta, StoryObj } from "@storybook/react-vite";

import { LoadingText } from "./LoadingText";

/**
 * The spinner-less muted "Loading…" text for async regions. Bare inline text by
 * default; `boxed` gives the dashed in-field placeholder treatment.
 */
const meta = {
	title: "UI/LoadingText",
	component: LoadingText,
	tags: ["autodocs"],
} satisfies Meta<typeof LoadingText>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Inline: Story = {
	args: { label: "Loading field…" },
};

export const Small: Story = {
	args: { size: "sm", label: "Loading field…" },
};

export const Boxed: Story = {
	args: { boxed: true, label: "Loading callouts…" },
};
