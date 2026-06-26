import type { Meta, StoryObj } from "@storybook/react-vite";
import { InlineEmpty } from "./InlineEmpty";
import { QueryRenderer } from "./QueryRenderer";
import { ApiError } from "@/types/api";

const meta = {
	title: "UI/QueryRenderer",
	component: QueryRenderer,
	tags: ["autodocs"],
	parameters: { layout: "padded" },
} satisfies Meta<typeof QueryRenderer>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Success: Story = {
	args: {
		children: <div className="rounded-md bg-surface-2 p-4 text-[13px]">Content loaded successfully.</div>,
	},
};

export const Loading: Story = {
	args: {
		isLoading: true,
		children: <div>You should not see this.</div>,
	},
};

export const Error: Story = {
	args: {
		error: new ApiError("Failed to load data from the server.", 500),
		children: <div>You should not see this.</div>,
	},
};

export const Empty: Story = {
	args: {
		isEmpty: true,
		empty: <InlineEmpty>No items found.</InlineEmpty>,
		children: <div>You should not see this.</div>,
	},
};

/** All four states side-by-side for visual reference. */
export const AllStates: Story = {
	render: () => (
		<div className="flex flex-col gap-6">
			<div>
				<p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-text-3">Loading</p>
				<QueryRenderer isLoading>
					<div />
				</QueryRenderer>
			</div>
			<div>
				<p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-text-3">Error</p>
				<QueryRenderer error={new ApiError("Could not reach the server.", 503)}>
					<div />
				</QueryRenderer>
			</div>
			<div>
				<p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-text-3">Empty</p>
				<QueryRenderer isEmpty empty={<InlineEmpty>No records found.</InlineEmpty>}>
					<div />
				</QueryRenderer>
			</div>
			<div>
				<p className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-text-3">Success</p>
				<QueryRenderer>
					<div className="rounded-md bg-surface-2 p-4 text-[13px]">Data rendered successfully.</div>
				</QueryRenderer>
			</div>
		</div>
	),
};
