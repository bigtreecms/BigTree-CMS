import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { Button } from "./Button";
import { ConfirmDialog } from "./ConfirmDialog";

/**
 * Modal confirmation built on Radix Dialog (focus trap, ESC, scrim). Renders its
 * own `Button` footer; `variant="danger"` swaps the confirm CTA to the solid
 * destructive button. Open state is controlled by the host.
 */
const meta = {
	title: "UI/ConfirmDialog",
	component: ConfirmDialog,
	tags: ["autodocs"],
	args: {
		open: false,
		onOpenChange: () => {},
		title: "Delete this page?",
		description: "This permanently removes the page and its children. This cannot be undone.",
		confirmLabel: "Delete",
		variant: "danger",
		onConfirm: () => {},
	},
	render: (args) => {
		const [open, setOpen] = useState(false);

		return (
			<>
				<Button variant="secondary" onClick={() => setOpen(true)}>
					Open dialog
				</Button>
				<ConfirmDialog {...args} open={open} onOpenChange={setOpen} />
			</>
		);
	},
} satisfies Meta<typeof ConfirmDialog>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Destructive confirm — solid red CTA. */
export const Danger: Story = {};

/** Neutral confirm — accent CTA. */
export const Default: Story = {
	args: {
		title: "Publish changes?",
		description: "Your edits will go live immediately.",
		confirmLabel: "Publish",
		variant: "default",
	},
};
