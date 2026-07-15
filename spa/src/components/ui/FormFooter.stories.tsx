import type { Meta, StoryObj } from "@storybook/react-vite";
import { Trash2 } from "lucide-react";

import { Button } from "./Button";
import { FormFooter } from "./FormFooter";

/**
 * The standard edit-page footer pair — a ghost Cancel and a primary submit that
 * composes the `Button` `loading` prop. Lives in a `FormShell` `footer` slot
 * (a `flex justify-end gap-2` bar); the stories wrap it in that bar so the
 * layout matches what ships.
 */
const meta = {
	title: "UI/FormFooter",
	component: FormFooter,
	tags: ["autodocs"],
	decorators: [
		(Story) => (
			<div className="flex justify-end gap-2 rounded-md border border-border bg-surface-2 px-4 py-3">
				<Story />
			</div>
		),
	],
	args: {
		cancelTo: "/feeds",
		submitLabel: "Save feed",
	},
} satisfies Meta<typeof FormFooter>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** Busy state — submit shows a spinner and the loading label, and is disabled. */
export const Loading: Story = {
	args: { loading: true, loadingLabel: "Saving…" },
};

/** A validity guard disables submit without putting it in the loading state. */
export const Disabled: Story = {
	args: { disabled: true },
};

/** A destructive action rendered far-left via the `extra` slot. */
export const WithDelete: Story = {
	args: {
		extra: (
			<Button icon={<Trash2 size={13} />} variant="dangerGhost">
				Delete
			</Button>
		),
	},
};
