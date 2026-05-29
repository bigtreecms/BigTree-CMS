import { useQueries } from "@tanstack/react-query";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ContentAlertsCard } from "@/components/dashboard/ContentAlertsCard";
import { TrafficCard } from "@/components/dashboard/TrafficCard";
import { PendingChangesCard } from "@/components/dashboard/PendingChangesCard";
import { UnreadMessagesCard } from "@/components/dashboard/UnreadMessagesCard";
import { dashboardApi, messagesApi, pendingChangesApi } from "@/api/endpoints/dashboard";
import { useAuthStore } from "@/auth/store";
import { isAdmin } from "@/lib/permissions";

/**
 * Dashboard — pixel port of the prototype's `dashboard-screen.jsx`.
 *
 * Three section cards stacked vertically:
 *   1. Recent traffic — 14-day bar chart from /dashboard/analytics
 *   2. Pending changes — two columns (your approval / awaiting publisher)
 *   3. Unread messages — table from /messages?folder=in (filtered to unread)
 *
 * Uses useQueries so the four independent fetches are parallelized; each
 * card handles its own loading/error/empty states inline so the page doesn't
 * block on the slowest endpoint.
 */
export const Dashboard = () => {
	const userName = useAuthStore((s) => s.user?.name);
	const currentUserId = useAuthStore((s) => s.user?.id ?? 0);
	const admin = isAdmin(useAuthStore((s) => s.user));
	const firstName = userName?.split(" ")[0] ?? "there";

	const [summaryQ, analyticsQ, pendingQ, messagesQ, alertsQ] = useQueries({
		queries: [
			{
				queryKey: ["dashboard", "summary"],
				queryFn: dashboardApi.summary,
			},
			{
				queryKey: ["dashboard", "analytics"],
				queryFn: dashboardApi.analytics,
				// /dashboard/analytics is Administrator-only (server returns 403 for
				// level 0); don't fire it — and don't render the card — for non-admins.
				enabled: admin,
			},
			{
				queryKey: ["pending-changes", "list", { mine: false }],
				queryFn: () => pendingChangesApi.list(),
			},
			{
				queryKey: ["messages", "list", { folder: "in" }],
				queryFn: () => messagesApi.list({ folder: "in", per_page: 10 }),
			},
			{
				queryKey: ["dashboard", "content-alerts"],
				queryFn: () => dashboardApi.contentAlerts(),
			},
		],
	});

	return (
		<div className="mx-auto max-w-screen-xl px-6 py-4">
			<Breadcrumb items={[{ label: "Dashboard" }]} />
			<PageHead title="Dashboard" sub={`Welcome back, ${firstName}.`} />

			<div className="flex flex-col gap-4">
				{admin && (
					<TrafficCard
						data={analyticsQ.data}
						loading={analyticsQ.isLoading}
						error={analyticsQ.error}
					/>
				)}
				<PendingChangesCard
					summary={summaryQ.data}
					pending={pendingQ.data ?? []}
					loading={summaryQ.isLoading || pendingQ.isLoading}
					error={summaryQ.error ?? pendingQ.error}
				/>
				<ContentAlertsCard
					alerts={alertsQ.data ?? []}
					loading={alertsQ.isLoading}
					error={alertsQ.error}
				/>
				<UnreadMessagesCard
					messages={messagesQ.data?.data ?? []}
					currentUserId={currentUserId}
					loading={messagesQ.isLoading}
					error={messagesQ.error}
				/>
			</div>
		</div>
	);
};
