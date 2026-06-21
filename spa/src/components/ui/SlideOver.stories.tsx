import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { Button } from "./Button";
import { Field } from "./Field";
import { SlideOver } from "./SlideOver";
import { TextInput } from "./TextInput";

/**
 * Right-edge drawer built on Radix Dialog (focus trap, ESC, scrim) — for pickers
 * and quick-edit panels. Optional `footer` and a `width` of `sm`/`md`/`lg`/`xl`.
 */
const meta = {
	title: "UI/SlideOver",
	component: SlideOver,
	tags: ["autodocs"],
	args: {
		open: false,
		onOpenChange: () => {},
		title: "Quick edit",
		description: "Update the page metadata without leaving the list.",
		width: "md",
		children: null,
	},
	render: (args) => {
		const [open, setOpen] = useState(false);

		return (
			<>
				<Button variant="secondary" onClick={() => setOpen(true)}>
					Open panel
				</Button>
				<SlideOver
					{...args}
					open={open}
					onOpenChange={setOpen}
					footer={
						<div className="flex justify-end gap-2">
							<Button variant="secondary" onClick={() => setOpen(false)}>
								Cancel
							</Button>
							<Button variant="primary" onClick={() => setOpen(false)}>
								Save
							</Button>
						</div>
					}
				>
					<div className="flex flex-col gap-4">
						<Field label="Title">
							<TextInput defaultValue="About us" />
						</Field>
						<Field label="Navigation title">
							<TextInput defaultValue="About" />
						</Field>
					</div>
				</SlideOver>
			</>
		);
	},
} satisfies Meta<typeof SlideOver>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Wide: Story = {
	args: { width: "xl", title: "Media library" },
};
