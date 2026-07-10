import { createMemoryRouter, RouterProvider } from "react-router-dom";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { UnsavedChangesGuard } from "./UnsavedChangesGuard";

/**
 * Drop-in guard that warns before leaving a form with unsaved edits. The confirm
 * dialog only opens once the data-router `useBlocker` parks a real navigation, so
 * in isolation it renders nothing — mount it once per page and feed it the form's
 * `isDirty` flag. The panel below stands in for the surrounding form. (The guard
 * requires a data router, so this story supplies its own via `RouterProvider`.)
 */
const meta = {
	title: "UI/UnsavedChangesGuard",
	component: UnsavedChangesGuard,
	tags: ["autodocs"],
	parameters: { router: false },
	decorators: [
		(Story) => {
			const router = createMemoryRouter(
				[
					{
						path: "*",
						element: (
							<div className="rounded-md border border-border bg-surface p-6 text-[13px] text-text-2">
								<p>The edit form lives here; the guard is mounted alongside it.</p>
								<Story />
							</div>
						),
					},
				],
				{ initialEntries: ["/"] }
			);

			return <RouterProvider router={router} />;
		},
	],
} satisfies Meta<typeof UnsavedChangesGuard>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Clean form — nothing is blocked. */
export const Pristine: Story = {
	args: { isDirty: false },
};

/** Dirty form — a navigation attempt would raise the discard prompt. */
export const Dirty: Story = {
	args: { isDirty: true },
};
