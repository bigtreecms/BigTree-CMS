import type { Meta, StoryObj } from "@storybook/react-vite";

import { Button } from "./Button";
import { FormShell } from "./FormShell";
import { TextField } from "./TextField";

/**
 * Standard edit-screen layout: optional header bar, a body, and a sticky footer
 * pinned to the bottom of the viewport so Save/Cancel stay reachable while
 * scrolling. `bounded` (default) applies a max-width; pass `onSubmit` to wrap
 * the body in a `<form>`.
 */
const meta = {
	title: "UI/FormShell",
	component: FormShell,
	tags: ["autodocs"],
	args: {
		header: <span className="font-medium text-text">Edit user</span>,
		children: (
			<div className="flex flex-col gap-4">
				<TextField label="Name" value="Ada Lovelace" onChange={() => {}} />
				<TextField label="Email" value="ada@example.com" onChange={() => {}} />
			</div>
		),
		footer: (
			<>
				<Button variant="secondary">Cancel</Button>
				<Button variant="primary">Save changes</Button>
			</>
		),
	},
} satisfies Meta<typeof FormShell>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** Without a header — just body + sticky footer. */
export const NoHeader: Story = {
	args: { header: undefined },
};

/** `bounded={false}` drops the max-width so the shell fills its container. */
export const FullWidth: Story = {
	args: { bounded: false },
};
