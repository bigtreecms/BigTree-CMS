import { useState } from "react";
import type { Meta, StoryObj } from "@storybook/react-vite";

import { Button } from "./Button";
import { Field } from "./Field";
import { Modal } from "./Modal";
import { TextInput } from "./TextInput";

/**
 * Centered modal dialog built on Radix Dialog (focus trap, ESC, scrim) — the
 * centered twin of `SlideOver`. `layout="card"` gives a single padded surface for
 * confirm/form dialogs; `layout="bars"` gives bordered header/footer bars around
 * full-bleed content (image croppers). `size` is `sm`/`md`/`lg`/`xl`; `scrim`
 * darkens the backdrop for media dialogs. This is the shared scaffold behind
 * `ConfirmDialog`, `PasswordChangeDialog`, `CropModal`, and `FieldCropModal`.
 */
const meta = {
	title: "UI/Modal",
	component: Modal,
	tags: ["autodocs"],
	args: {
		open: false,
		onOpenChange: () => {},
		title: "Modal title",
		description: "A short supporting line of context for the dialog.",
		size: "sm",
		layout: "card",
		children: null,
	},
	render: (args) => {
		const [open, setOpen] = useState(false);

		return (
			<>
				<Button variant="secondary" onClick={() => setOpen(true)}>
					Open modal
				</Button>
				<Modal {...args} open={open} onOpenChange={setOpen} />
			</>
		);
	},
} satisfies Meta<typeof Modal>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Padded card with a footer button row — the confirm/form shape. */
export const Card: Story = {
	render: (args) => {
		const [open, setOpen] = useState(false);

		return (
			<>
				<Button variant="secondary" onClick={() => setOpen(true)}>
					Open card modal
				</Button>
				<Modal
					{...args}
					open={open}
					onOpenChange={setOpen}
					footer={
						<>
							<Button variant="secondary" onClick={() => setOpen(false)}>
								Cancel
							</Button>
							<Button variant="primary" onClick={() => setOpen(false)}>
								Save
							</Button>
						</>
					}
				>
					<Field label="Name">
						<TextInput defaultValue="About us" />
					</Field>
				</Modal>
			</>
		);
	},
};

/** Bordered header/footer bars around full-bleed content, dark scrim, close X. */
export const Bars: Story = {
	args: {
		title: "Crop image",
		description: "Drag inside the image to position the crop.",
		size: "xl",
		layout: "bars",
		scrim: "dark",
		showClose: true,
	},
	render: (args) => {
		const [open, setOpen] = useState(false);

		return (
			<>
				<Button variant="secondary" onClick={() => setOpen(true)}>
					Open bars modal
				</Button>
				<Modal
					{...args}
					open={open}
					onOpenChange={setOpen}
					footer={
						<div className="flex justify-end gap-2">
							<Button variant="secondary" onClick={() => setOpen(false)}>
								Cancel
							</Button>
							<Button variant="primary" onClick={() => setOpen(false)}>
								Save crop
							</Button>
						</div>
					}
				>
					<div className="grid h-[320px] place-items-center bg-black text-[13px] text-white/70">
						Full-bleed content area
					</div>
				</Modal>
			</>
		);
	},
};
