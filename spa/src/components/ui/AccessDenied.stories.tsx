import type { Meta, StoryObj } from "@storybook/react-vite";

import { AccessDenied } from "./AccessDenied";

/**
 * "You don't have access" panel. Routes render this in place rather than
 * redirecting, so the user gets clear feedback about why they hit a wall. The
 * companion `RequireLevel` guard (same file) wraps a route element and renders
 * this on a failed `user.level` check — not storied here since it reads the auth
 * store.
 */
const meta = {
	title: "UI/AccessDenied",
	component: AccessDenied,
	tags: ["autodocs"],
} satisfies Meta<typeof AccessDenied>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const CustomMessage: Story = {
	args: {
		title: "Developer access required",
		message: "This section is limited to users with developer permissions.",
	},
};
