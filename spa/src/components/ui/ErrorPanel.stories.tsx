import type { Meta, StoryObj } from "@storybook/react-vite";

import { ApiError } from "@/types/api";

import { ErrorPanel } from "./ErrorPanel";

/**
 * Inline error surface for failed loads. When handed an `ApiError` it also
 * renders the status / code / request_id triplet for debugging; any other
 * thrown value falls back to just its message.
 */
const meta = {
	title: "UI/ErrorPanel",
	component: ErrorPanel,
	tags: ["autodocs"],
	decorators: [
		(Story) => (
			<div style={{ maxWidth: 480 }}>
				<Story />
			</div>
		),
	],
} satisfies Meta<typeof ErrorPanel>;

export default meta;
type Story = StoryObj<typeof meta>;

/** An `ApiError` shows the debugging metadata. */
export const FromApiError: Story = {
	args: {
		error: new ApiError(
			422,
			{
				errors: [
					{ code: "validation_error", field: "title", message: "Title is required." },
				],
				meta: { request_id: "req_a1b2c3d4" },
			},
			"Request failed"
		),
	},
};

/** A plain `Error` shows just the message. */
export const FromPlainError: Story = {
	args: { error: new Error("Network request failed — please retry.") },
};
