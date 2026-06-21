import type { Meta, StoryObj } from "@storybook/react-vite";

import { LockBanner } from "./LockBanner";

/**
 * Read-only banner shown on an edit screen while another user holds the row's
 * edit lock. Offers a confirmed "Unlock" takeover (its own `ConfirmDialog`),
 * mirroring the legacy admin's `_locked.php` interstitial.
 */
const meta = {
	title: "UI/LockBanner",
	component: LockBanner,
	tags: ["autodocs"],
	args: {
		owner: { id: 2, name: "Jane Editor", email: "jane@example.com" },
		lockedAt: new Date(Date.now() - 7 * 60 * 1000).toISOString(),
		onUnlock: () => {},
	},
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 640 }}>
				<Story />
			</div>
		),
	],
} satisfies Meta<typeof LockBanner>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** When the server can't attribute the lock, it reads "another user". */
export const UnknownOwner: Story = {
	args: { owner: null, lockedAt: null },
};
