import type { Meta, StoryObj } from "@storybook/react-vite";

import { PermissionRow } from "./PermissionRow";
import { PermissionTreeHeader } from "./PermissionTreeHeader";

/**
 * Shared grid data-row for the Page / Module / Resource permission trees.
 * Two visual variants:
 *   - **Standard** — white background, used for top-level module rows and
 *     page/folder rows at all depths.
 *   - **Nested** — dimmed background, fixed deep indent; used for GBP
 *     category sub-rows inside an expanded module row.
 *
 * The `depth` prop adds 16 px of left-padding per level (on top of the 12 px
 * base), matching the page/resource tree visual hierarchy.
 */
const COLUMNS_3 = "minmax(0,1fr) repeat(3, 80px)";
const COLUMNS_4 = "minmax(0,1fr) repeat(4, 80px)";

const Placeholder = ({ n }: { n: number }) => (
	<>
		{Array.from({ length: n }).map((_, i) => (
			<div key={i} className="h-4 rounded bg-surface-2" />
		))}
	</>
);

const meta = {
	title: "Users/PermissionRow",
	component: PermissionRow,
	tags: ["autodocs"],
	decorators: [
		(Story) => (
			<div className="w-[560px] overflow-hidden rounded-md border border-border">
				<Story />
			</div>
		),
	],
} satisfies Meta<typeof PermissionRow>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Standard row (module list, 3 permission columns). */
export const Standard: Story = {
	args: {
		columns: COLUMNS_3,
		children: (
			<>
				<span className="truncate text-text">News Articles</span>
				<Placeholder n={3} />
			</>
		),
	},
};

/** Multiple standard rows stacked — first row suppresses its top border. */
export const MultipleRows: Story = {
	render: () => (
		<>
			<PermissionTreeHeader columns={COLUMNS_3}>
				<div>Module</div>
				<div className="text-center">Publisher</div>
				<div className="text-center">Editor</div>
				<div className="text-center">No Access</div>
			</PermissionTreeHeader>
			<div className="border-x border-b border-border">
				{["News Articles", "Blog Posts", "Team Members"].map((name) => (
					<PermissionRow key={name} columns={COLUMNS_3}>
						<span className="truncate text-text">{name}</span>
						<Placeholder n={3} />
					</PermissionRow>
				))}
			</div>
		</>
	),
};

/** Nested (GBP category) row — dimmed background, fixed deep indent. */
export const Nested: Story = {
	render: () => (
		<>
			<PermissionTreeHeader columns={COLUMNS_3}>
				<div>Module</div>
				<div className="text-center">Publisher</div>
				<div className="text-center">Editor</div>
				<div className="text-center">No Access</div>
			</PermissionTreeHeader>
			<div className="border-x border-b border-border">
				<PermissionRow columns={COLUMNS_3}>
					<span className="truncate text-text">News Articles</span>
					<Placeholder n={3} />
				</PermissionRow>
				<PermissionRow columns={COLUMNS_3} nested>
					<span className="truncate text-text-2">
						<span className="text-text-3">Region:</span> North America
					</span>
					<Placeholder n={3} />
				</PermissionRow>
				<PermissionRow columns={COLUMNS_3} nested>
					<span className="truncate text-text-2">
						<span className="text-text-3">Region:</span> Europe
					</span>
					<Placeholder n={3} />
				</PermissionRow>
			</div>
		</>
	),
};

/** Depth-indented rows — page / resource folder tree style. */
export const WithDepth: Story = {
	render: () => (
		<>
			<PermissionTreeHeader columns={COLUMNS_4}>
				<div>Folder</div>
				<div className="text-center">Creator</div>
				<div className="text-center">Consumer</div>
				<div className="text-center">No Access</div>
				<div className="text-center">Inherit</div>
			</PermissionTreeHeader>
			<div className="border-x border-b border-border">
				<PermissionRow columns={COLUMNS_4} depth={0}>
					<span className="truncate text-text">Home Folder</span>
					<Placeholder n={4} />
				</PermissionRow>
				<PermissionRow columns={COLUMNS_4} depth={1}>
					<span className="truncate text-text">Images</span>
					<Placeholder n={4} />
				</PermissionRow>
				<PermissionRow columns={COLUMNS_4} depth={2}>
					<span className="truncate text-text">2024</span>
					<Placeholder n={4} />
				</PermissionRow>
				<PermissionRow columns={COLUMNS_4} depth={1}>
					<span className="truncate text-text">Documents</span>
					<Placeholder n={4} />
				</PermissionRow>
			</div>
		</>
	),
};
