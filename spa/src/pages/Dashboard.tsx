import { useMemo, type ReactNode } from "react";
import { useQueries } from "@tanstack/react-query";
import {
	Activity,
	Bell,
	ChevronRight,
	ExternalLink,
	FileText,
	Mail,
	type LucideIcon,
} from "lucide-react";
import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import {
	dashboardApi,
	messagesApi,
	pendingChangesApi,
	type DashboardSummary,
	type AnalyticsResponse,
	type PendingChange,
	type Message,
} from "@/api/endpoints/dashboard";
import { ApiError } from "@/types/api";
import { useAuthStore } from "@/auth/store";

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
export function Dashboard() {
	const userName = useAuthStore((s) => s.user?.name);
	const currentUserId = useAuthStore((s) => s.user?.id ?? 0);
	const firstName = userName?.split(" ")[0] ?? "there";

	const [summaryQ, analyticsQ, pendingQ, messagesQ] = useQueries({
		queries: [
			{ queryKey: ["dashboard", "summary"], queryFn: dashboardApi.summary },
			{ queryKey: ["dashboard", "analytics"], queryFn: dashboardApi.analytics },
			{ queryKey: ["pending-changes", { mine: false }], queryFn: () => pendingChangesApi.list() },
			{
				queryKey: ["messages", { folder: "in" }],
				queryFn: () => messagesApi.list({ folder: "in", per_page: 10 }),
			},
		],
	});

	return (
		<div className="mx-auto max-w-screen-xl px-6 py-4">
			<Breadcrumb items={[{ label: "Dashboard" }]} />
			<PageHead title="Dashboard" sub={`Welcome back, ${firstName}.`} />

			<div className="flex flex-col gap-4">
				<TrafficCard
					data={analyticsQ.data}
					loading={analyticsQ.isLoading}
					error={analyticsQ.error}
				/>
				<PendingChangesCard
					summary={summaryQ.data}
					pending={pendingQ.data ?? []}
					loading={summaryQ.isLoading || pendingQ.isLoading}
					error={summaryQ.error ?? pendingQ.error}
				/>
				<UnreadMessagesCard
					messages={messagesQ.data ?? []}
					currentUserId={currentUserId}
					loading={messagesQ.isLoading}
					error={messagesQ.error}
				/>
			</div>
		</div>
	);
}

/* ─── Section card chrome ─────────────────────────────────────────────── */

interface DashCardProps {
	icon: LucideIcon;
	title: string;
	sub?: ReactNode;
	action?: ReactNode;
	children: ReactNode;
}

function DashCard({ icon: Icon, title, sub, action, children }: DashCardProps) {
	return (
		<section className="overflow-hidden rounded-lg border border-border bg-surface">
			<header className="flex flex-wrap items-center gap-2.5 gap-y-1.5 border-b border-border bg-surface-2 px-4 py-3">
				<span className="grid h-[22px] w-[22px] place-items-center rounded-md bg-accent-soft text-accent">
					<Icon size={14} />
				</span>
				<h2 className="text-[12px] font-semibold uppercase tracking-[0.08em] text-text">{title}</h2>
				{sub && <span className="text-[12.5px] text-text-3">{sub}</span>}
				<span className="flex-1" />
				{action}
			</header>
			<div className="p-4">{children}</div>
		</section>
	);
}

function CardError({ error }: { error: unknown }) {
	const message = error instanceof ApiError ? error.message : "Failed to load.";
	return <div className="text-[12.5px] text-danger">{message}</div>;
}

function CardEmpty({ icon: Icon, label }: { icon: LucideIcon; label: string }) {
	return (
		<div className="flex items-center gap-2.5 rounded-md border border-dashed border-border bg-surface-2 px-3.5 py-[18px] text-[13px] text-text-3">
			<Icon size={20} className="text-text-4" />
			<span>{label}</span>
		</div>
	);
}

/** Small inline accent-colored link button — matches prototype's `.link`. */
function LinkBtn({ children, onClick }: { children: ReactNode; onClick?: () => void }) {
	return (
		<button
			type="button"
			onClick={onClick}
			className="-mx-1.5 -my-1 inline-flex items-center gap-0.5 rounded-[5px] bg-transparent px-1.5 py-1 text-[12px] font-medium text-accent transition-colors hover:bg-accent-soft"
		>
			{children}
		</button>
	);
}

/** Compact secondary button used in card headers. */
function SmallBtn({ children, onClick }: { children: ReactNode; onClick?: () => void }) {
	return (
		<button
			type="button"
			onClick={onClick}
			className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-2.5 py-1 text-[12px] font-medium text-text-2 transition-colors hover:border-border-strong hover:bg-hover"
		>
			{children}
		</button>
	);
}

/* ─── 1. Traffic bar chart ──────────────────────────────────────────────── */

function TrafficCard({
	data,
	loading,
	error,
}: {
	data: AnalyticsResponse | undefined;
	loading: boolean;
	error: unknown;
}) {
	// Build the 14-day series from cache.two_week (keyed YYYYMMDD).
	const series = useMemo(() => {
		const twoWeek = data?.cache?.two_week;
		if (!twoWeek) return null;
		const entries = Object.entries(twoWeek).sort(([a], [b]) => a.localeCompare(b));
		return entries.slice(-14).map(([yyyymmdd, visits]) => {
			// "20260521" → "5/21"
			const month = Number(yyyymmdd.slice(4, 6));
			const day = Number(yyyymmdd.slice(6, 8));
			return { date: `${month}/${day}`, visits };
		});
	}, [data]);

	const total14d = useMemo(
		() => (series ? series.reduce((s, d) => s + d.visits, 0) : 0),
		[series],
	);

	return (
		<DashCard
			icon={Activity}
			title="Recent traffic"
			sub="Visits in the past two weeks"
			action={
				series && (
					<div className="flex items-center gap-3">
						<span className="text-[12px] text-text-3 tabular-nums">
							<b className="font-semibold text-text">{total14d.toLocaleString()}</b> total
						</span>
						<SmallBtn>
							<ExternalLink size={12} />
							View analytics
						</SmallBtn>
					</div>
				)
			}
		>
			{loading ? (
				<div className="text-[12.5px] text-text-3">Loading…</div>
			) : error ? (
				<CardError error={error} />
			) : !data?.configured ? (
				<div className="rounded-md border border-dashed border-border bg-surface-2 px-3.5 py-[14px] text-[12.5px] leading-[1.55] text-text-3">
					Google Analytics isn't connected yet. Connect it in{" "}
					<span className="font-mono text-text-2">Developer → Analytics</span> to see traffic.
				</div>
			) : !series || series.length === 0 ? (
				<CardEmpty
					icon={Activity}
					label="No traffic data yet — check back after the next cache refresh."
				/>
			) : (
				<TrafficBars series={series} />
			)}
		</DashCard>
	);
}

function TrafficBars({ series }: { series: Array<{ date: string; visits: number }> }) {
	const max = Math.max(...series.map((d) => d.visits), 1);
	return (
		<div
			className="grid h-[200px] gap-1.5 pt-1"
			style={{ gridTemplateColumns: `repeat(${series.length}, minmax(0, 1fr))` }}
		>
			{series.map((d) => {
				const pct = (d.visits / max) * 100;
				return (
					<div
						key={d.date}
						className="group flex min-w-0 flex-col"
						title={`${d.visits.toLocaleString()} visits on ${d.date}`}
					>
						<div className="relative flex flex-1 items-end">
							<div
								className="flex w-full justify-center rounded-t-[5px] bg-accent pt-1 transition-[filter,background] group-hover:brightness-110"
								style={{ height: `${pct}%`, minHeight: "22px" }}
							>
								<span className="whitespace-nowrap text-[10.5px] font-semibold text-accent-fg tabular-nums">
									{d.visits.toLocaleString()}
								</span>
							</div>
						</div>
						<div className="mt-1.5 text-center text-[10.5px] text-text-3 tabular-nums">{d.date}</div>
					</div>
				);
			})}
		</div>
	);
}

/* ─── 2. Pending changes ────────────────────────────────────────────────── */

function PendingChangesCard({
	summary,
	pending,
	loading,
	error,
}: {
	summary: DashboardSummary | undefined;
	pending: PendingChange[];
	loading: boolean;
	error: unknown;
}) {
	const groups = useMemo(() => groupPendingByTable(pending), [pending]);
	const totalPending =
		summary?.pending_changes.publishable ?? groups.reduce((s, g) => s + g.count, 0);
	const myPending = summary?.pending_changes.mine ?? 0;

	return (
		<DashCard
			icon={Bell}
			title="Pending changes"
			sub={loading ? "Loading…" : `${totalPending} awaiting review`}
			action={
				<SmallBtn>
					View all pending changes
					<ChevronRight size={11} />
				</SmallBtn>
			}
		>
			{error ? (
				<CardError error={error} />
			) : (
				<div className="grid grid-cols-1 gap-6 md:grid-cols-2">
					<div className="flex min-w-0 flex-col gap-2">
						<div className="mb-0.5 border-b border-border pb-1.5 text-[10.5px] font-semibold uppercase tracking-[0.07em] text-text-3">
							Changes pending your approval
						</div>
						{groups.length === 0 ? (
							<EmptyPending label="No pending changes to review right now." />
						) : (
							<ul className="m-0 flex list-none flex-col gap-0.5 p-0">
								{groups.map((g) => (
									<li
										key={g.key}
										className="flex items-center gap-2.5 rounded-[7px] p-2.5 transition-colors hover:bg-surface-2"
									>
										<span className="grid h-[26px] w-[26px] place-items-center rounded-md bg-surface-3 text-text-2">
											<FileText size={14} />
										</span>
										<span className="min-w-0 flex-1 text-[13px] text-text">
											<b className="font-semibold">{g.count}</b> change{g.count === 1 ? "" : "s"}{" "}
											for {g.label}
										</span>
										<LinkBtn>
											View changes <ChevronRight size={11} />
										</LinkBtn>
									</li>
								))}
							</ul>
						)}
					</div>

					<div className="flex min-w-0 flex-col gap-2">
						<div className="mb-0.5 border-b border-border pb-1.5 text-[10.5px] font-semibold uppercase tracking-[0.07em] text-text-3">
							Awaiting publisher approval
						</div>
						{myPending === 0 ? (
							<EmptyPending label="You have no changes awaiting a publisher's approval." />
						) : (
							<div className="rounded-md border border-border bg-surface px-3.5 py-3 text-[13px] text-text">
								<b className="font-semibold">{myPending}</b> of your change
								{myPending === 1 ? "" : "s"} {myPending === 1 ? "is" : "are"} waiting for a publisher
								to review.
							</div>
						)}
					</div>
				</div>
			)}
		</DashCard>
	);
}

function EmptyPending({ label }: { label: string }) {
	return (
		<div className="flex h-full min-h-[110px] items-center rounded-md border border-dashed border-border bg-surface-2 p-3.5 text-[12.5px] leading-[1.55] text-text-3">
			{label}
		</div>
	);
}

/** Group pending changes by table/module so we can show counts per category. */
function groupPendingByTable(pending: PendingChange[]) {
	const map = new Map<string, { key: string; label: string; count: number }>();
	for (const p of pending) {
		const key = p.module ? `module:${p.module}` : `table:${p.table}`;
		const label = humanizeTable(p.table);
		const cur = map.get(key);
		if (cur) cur.count++;
		else map.set(key, { key, label, count: 1 });
	}
	return [...map.values()].sort((a, b) => b.count - a.count);
}

function humanizeTable(table: string): string {
	if (table === "bigtree_pages") return "Pages";
	if (table.startsWith("bigtree_")) {
		return table
			.slice("bigtree_".length)
			.replace(/_/g, " ")
			.replace(/\b\w/g, (c) => c.toUpperCase());
	}
	return table.replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
}

/* ─── 3. Unread messages ────────────────────────────────────────────────── */

function UnreadMessagesCard({
	messages,
	currentUserId,
	loading,
	error,
}: {
	messages: Message[];
	currentUserId: number;
	loading: boolean;
	error: unknown;
}) {
	// Filter to genuinely-unread inbox items: addressed to me AND not yet in read_by.
	const unread = useMemo(
		() =>
			messages.filter(
				(m) => m.recipients.includes(currentUserId) && !m.read_by.includes(currentUserId),
			),
		[messages, currentUserId],
	);

	return (
		<DashCard
			icon={Mail}
			title="Unread messages"
			sub={
				loading
					? "Loading…"
					: unread.length === 0
						? "All caught up"
						: `${unread.length} unread`
			}
			action={
				<SmallBtn>
					View all messages
					<ChevronRight size={11} />
				</SmallBtn>
			}
		>
			{error ? (
				<CardError error={error} />
			) : unread.length === 0 ? (
				<CardEmpty icon={Mail} label="No unread messages" />
			) : (
				<MessagesTable messages={unread} />
			)}
		</DashCard>
	);
}

function MessagesTable({ messages }: { messages: Message[] }) {
	return (
		<div className="overflow-hidden rounded-md border border-border">
			<div className="grid h-[34px] grid-cols-[1.4fr_2fr_110px_80px_80px] items-center gap-x-3 border-b border-border bg-surface-2 px-3.5 text-[10.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
				<span>From</span>
				<span>Subject</span>
				<span>Date</span>
				<span>Time</span>
				<span />
			</div>
			{messages.map((m) => {
				const { date, time } = splitDateTime(m.date);
				return (
					<div
						key={m.id}
						className="grid min-h-[44px] grid-cols-[1.4fr_2fr_110px_80px_80px] items-center gap-x-3 border-b border-border px-3.5 text-[13px] transition-colors last:border-b-0 hover:bg-surface-2"
					>
						<span className="inline-flex items-center gap-2 font-medium">
							<span
								className="grid h-6 w-6 place-items-center rounded-full text-[10.5px] font-semibold text-white"
								style={{ background: avatarColor(m.sender) }}
							>
								{senderInitials(m.sender)}
							</span>
							<span>User #{m.sender}</span>
						</span>
						<span className="overflow-hidden text-ellipsis whitespace-nowrap">{m.subject}</span>
						<span className="text-[12px] text-text-3 tabular-nums">{date}</span>
						<span className="text-[12px] text-text-3 tabular-nums">{time}</span>
						<LinkBtn>
							View <ChevronRight size={11} />
						</LinkBtn>
					</div>
				);
			})}
		</div>
	);
}

function splitDateTime(iso: string): { date: string; time: string } {
	try {
		const d = new Date(iso.replace(" ", "T"));
		if (Number.isNaN(d.getTime())) return { date: iso, time: "" };
		const date = `${d.getMonth() + 1}/${d.getDate()}/${String(d.getFullYear()).slice(-2)}`;
		const time = d.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });
		return { date, time };
	} catch {
		return { date: iso, time: "" };
	}
}

/** Deterministic avatar color from a user id — same algo style as the prototype. */
function avatarColor(id: number): string {
	const hue = (id * 47) % 360;
	return `oklch(58% 0.11 ${hue})`;
}

/** Placeholder initials while we don't have user-detail lookups wired yet. */
function senderInitials(id: number): string {
	return `#${id}`.slice(-2);
}
