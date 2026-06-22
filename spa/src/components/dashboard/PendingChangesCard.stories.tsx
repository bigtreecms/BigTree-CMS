import type { Meta, StoryObj } from "@storybook/react-vite";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";

import { PendingChangesCard } from "./PendingChangesCard";
import type { DashboardSummary, PendingChange } from "@/api/endpoints/dashboard";

/**
 * Dashboard "Pending changes" card: lists recent pending changes with inline
 * Approve / Reject actions. Both approving and rejecting open a confirmation
 * dialog before the change is published or discarded.
 */
const meta = {
	title: "Dashboard/PendingChangesCard",
	component: PendingChangesCard,
	decorators: [
		(Story) => {
			const client = new QueryClient({
				defaultOptions: { queries: { retry: false } },
			});

			return (
				<QueryClientProvider client={client}>
					<Story />
				</QueryClientProvider>
			);
		},
	],
} satisfies Meta<typeof PendingChangesCard>;

export default meta;
type Story = StoryObj<typeof meta>;

const pending: PendingChange[] = [
	{
		id: 1,
		user: 2,
		date: "2026-06-21",
		title: "About Us",
		table: "btx_press",
		item_id: 14,
		type: "edit",
		module: "Press",
		pending_page_parent: 0,
	},
	{
		id: 2,
		user: 3,
		date: "2026-06-20",
		title: "New team member",
		table: "btx_team",
		item_id: null,
		type: "new",
		module: "Team",
		pending_page_parent: 0,
	},
];

const summary = {
	pending_changes: { mine: 1, publishable: 2 },
} as DashboardSummary;

export const Default: Story = {
	args: {
		summary,
		pending,
		loading: false,
		error: undefined,
	},
};

export const Empty: Story = {
	args: {
		summary: { pending_changes: { mine: 0, publishable: 0 } } as DashboardSummary,
		pending: [],
		loading: false,
		error: undefined,
	},
};
