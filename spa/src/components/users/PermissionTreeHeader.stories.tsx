import type { Meta, StoryObj } from "@storybook/react-vite";

import { PermissionTreeHeader } from "./PermissionTreeHeader";

/**
 * The shared bordered grid header row for the Page / Module / Resource
 * permission trees. Owns the canonical overline styling; callers pass the
 * matching `columns` grid template and the header cells.
 */
const meta = {
	title: "Users/PermissionTreeHeader",
	component: PermissionTreeHeader,
	tags: ["autodocs"],
	decorators: [
		(Story) => (
			<div className="w-[560px]">
				<Story />
			</div>
		),
	],
} satisfies Meta<typeof PermissionTreeHeader>;

export default meta;
type Story = StoryObj<typeof meta>;

/** The resource-folder tree header: one label column plus four permission columns. */
export const Resource: Story = {
	args: {
		columns: "minmax(0,1fr) repeat(4, 80px)",
		children: (
			<>
				<div>Folder</div>
				<div className="text-center">Creator</div>
				<div className="text-center">Consumer</div>
				<div className="text-center">No Access</div>
				<div className="text-center">Inherit</div>
			</>
		),
	},
};

/** The module tree header: three permission columns (modules have no "Inherit"). */
export const Module: Story = {
	args: {
		columns: "minmax(0,1fr) repeat(3, 80px)",
		children: (
			<>
				<div>Module</div>
				<div className="text-center">Publisher</div>
				<div className="text-center">Editor</div>
				<div className="text-center">No Access</div>
			</>
		),
	},
};

/** The page tree header: adds a "Content alerts" column before the permissions. */
export const Page: Story = {
	args: {
		columns: "minmax(0,1fr) 120px repeat(4, 80px)",
		children: (
			<>
				<div>Page</div>
				<div className="text-center">Content alerts</div>
				<div className="text-center">Publisher</div>
				<div className="text-center">Editor</div>
				<div className="text-center">No Access</div>
				<div className="text-center">Inherit</div>
			</>
		),
	},
};
