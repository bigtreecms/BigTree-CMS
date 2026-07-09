import type { Meta, StoryObj } from "@storybook/react-vite";

import { ApiError } from "@/types/api";

import { EditPageGuard } from "./EditPageGuard";

/**
 * The loading / error / content tri-state shared by the developer add-edit pages.
 * While the record loads it shows a full-page card spinner; on a failed fetch it
 * shows an `ErrorPanel`; otherwise it renders the form. Add-mode callers leave
 * `loading`/`error` falsy so the children pass straight through.
 */
const meta = {
	title: "UI/EditPageGuard",
	component: EditPageGuard,
	tags: ["autodocs"],
	args: {
		width: "narrow",
		children: (
			<div className="rounded-md border border-border bg-surface p-6 text-[13px] text-text-2">
				The editor form renders here.
			</div>
		),
	},
} satisfies Meta<typeof EditPageGuard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Content: Story = {};

export const LoadingState: Story = {
	args: { loading: true },
};

export const ErrorState: Story = {
	args: { error: new ApiError(404, null, "Callout not found") },
};
