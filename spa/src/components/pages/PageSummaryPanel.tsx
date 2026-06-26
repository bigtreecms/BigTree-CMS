import { useMemo, useState } from "react";
import { useQuery, type UseQueryResult } from "@tanstack/react-query";
import { ChevronRight, ExternalLink, HelpCircle } from "lucide-react";

import { pagesApi, type PageDetail, type PageSeoRating } from "@/api/endpoints/pages";
import { queryKeys } from "@/lib/queryKeys";
import { Badge, type BadgeTone } from "@/components/ui/Badge";
import { IconButton } from "@/components/ui/IconButton";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { formatNumber } from "@/lib/number";

/**
 * Collapsible "Properties" summary panel that sits above every page-section
 * view (Edit / Add / Revisions). Ports the design's `.props` block:
 *
 *   ┌── Properties ───────────────  [pill] Published · SEO 85% · 34k views ── ┐
 *   │ Status      SEO Rating       Content Age   30 Day Views                 │
 *   │ Published   85%              611 Days      34,561                       │
 *   │ Live URL                                    Page ID                     │
 *   │ https://…                                   #123                        │
 *   └────────────────────────────────────────────────────────────────────────┘
 *
 * All six properties are API-backed: Status / Content Age / Live URL / Page ID
 * come from the page payload, 30 Day Views from the cached `ga_page_views`
 * column (GA4 sync), and SEO Rating from GET /pages/{id}/seo-rating (the legacy
 * scoring algorithm). When a value genuinely isn't available yet (analytics not
 * connected, or a page that can't be rated) we fall back to an em-dash + tooltip.
 */

interface PageSummaryPanelProps {
	page: PageDetail | null;
	/**
	 * Override the displayed live URL. Default uses page.path (which is the
	 * site-relative path from PageService); the API doesn't expose www_root.
	 */
	liveUrl?: string;
	defaultOpen?: boolean;
}

type StatusTone = "ok" | "draft" | "warn" | "danger";

interface StatusInfo {
	label: string;
	tone: StatusTone;
}

const deriveStatus = (page: PageDetail | null): StatusInfo => {
	if (!page) {
		return { label: "—", tone: "draft" };
	}

	if (page.archived) {
		return { label: "Archived", tone: "warn" };
	}

	if (page.publish_at && new Date(page.publish_at) > new Date()) {
		return { label: "Scheduled", tone: "warn" };
	}

	return { label: "Published", tone: "ok" };
};

const daysSince = (iso: string | null | undefined): number | null => {
	if (!iso) {
		return null;
	}

	const then = Date.parse(iso);

	if (!Number.isFinite(then)) {
		return null;
	}

	return Math.max(0, Math.round((Date.now() - then) / (1000 * 60 * 60 * 24)));
};

const TONE_BADGE: Record<StatusTone, BadgeTone> = {
	ok: "success",
	draft: "info",
	warn: "warn",
	danger: "danger",
};

const TONE_TEXT: Record<StatusTone, string> = {
	ok: "text-success",
	draft: "text-info",
	warn: "text-warn",
	danger: "text-danger",
};

export const PageSummaryPanel = ({ page, liveUrl, defaultOpen = false }: PageSummaryPanelProps) => {
	const [open, setOpen] = useState(defaultOpen);

	const status = useMemo(() => deriveStatus(page), [page]);
	const ageDays = useMemo(() => (page ? daysSince(page.updated_at) : null), [page]);

	const url = liveUrl ?? (page ? page.path : "");

	// Score is computed server-side; only fetch once the panel is open and we have
	// a saved page (id > 0) to rate.
	const seoQuery = useQuery({
		queryKey: queryKeys.pages.seoRating(page?.id),
		queryFn: () => pagesApi.seoRating(page!.id),
		enabled: open && Boolean(page?.id),
		staleTime: 60_000,
	});

	return (
		<section
			className="mb-4 overflow-hidden rounded-lg border border-border bg-surface shadow-sm"
			data-open={open}
		>
			<button
				type="button"
				className={`flex w-full flex-wrap items-center gap-x-2.5 gap-y-1.5 px-4 py-2.5 text-left transition-colors ${
					open
						? "border-b border-border bg-surface-2"
						: "border-b border-transparent hover:bg-surface-2"
				}`}
				onClick={() => setOpen((v) => !v)}
				aria-expanded={open}
			>
				<span
					className={`grid size-4  place-items-center rounded text-text-3 transition-transform ${
						open ? "rotate-90 bg-accent-soft text-accent" : ""
					}`}
				>
					<ChevronRight size={12} />
				</span>
				<span
					className={`text-[11px] font-bold uppercase tracking-[0.09em] ${
						open ? "text-text" : "text-text-3"
					}`}
				>
					Properties
				</span>

				{!open && (
					<span className="ml-auto inline-flex items-center gap-2 text-[12px] text-text-3">
						<Badge tone={TONE_BADGE[status.tone]} dot>
							{status.label}
						</Badge>
						<span className="text-text-4">·</span>
						<span className="whitespace-nowrap text-text-2">
							{ageDays !== null ? `${ageDays} days old` : "Age unknown"}
						</span>
						{page?.id !== undefined && (
							<>
								<span className="text-text-4">·</span>
								<span className="font-mono text-text-2">#{page.id}</span>
							</>
						)}
					</span>
				)}
			</button>

			{open && page && (
				<div className="grid grid-cols-2 gap-x-7 gap-y-3.5 p-4 md:grid-cols-4">
					<Prop label="Status">
						<span
							className={`inline-flex items-center gap-1.5 text-[14px] font-medium ${TONE_TEXT[status.tone]}`}
						>
							<span className="size-1.5 rounded-full bg-current" />
							{status.label}
						</span>
					</Prop>

					<Prop label="SEO Rating">
						<SeoRatingValue query={seoQuery} />
					</Prop>

					<Prop label="Content Age">
						<span
							className={`text-[14px] font-medium ${
								ageDays !== null && ageDays > 365 ? "text-warn" : "text-text-2"
							}`}
						>
							{ageDays !== null ? (
								<>
									{formatNumber(ageDays)}
									<span className="ml-1 text-[12px] font-normal text-text-3">
										Days
									</span>
								</>
							) : (
								"—"
							)}
						</span>
					</Prop>

					<Prop label="30 Day Views">
						{typeof page.ga_page_views === "number" ? (
							<span className="text-[14px] font-medium text-text-2 tabular-nums">
								{formatNumber(page.ga_page_views)}
							</span>
						) : (
							<UnknownValue title="Connect Google Analytics to track page views — updates on the next sync." />
						)}
					</Prop>

					<Prop label="Live URL" span={2}>
						{url ? (
							<a
								href={"/" + url.replace(/^\//, "")}
								target="_blank"
								rel="noopener noreferrer"
								className="inline-flex min-w-0 items-center gap-1 truncate text-[14px] font-medium text-accent hover:underline"
								title={url}
							>
								<span className="truncate">/{url.replace(/^\//, "")}</span>
								<ExternalLink size={11} className="shrink-0" />
							</a>
						) : (
							<span className="text-[14px] font-medium text-text-3">—</span>
						)}
					</Prop>

					<Prop label="Page ID">
						<span className="font-mono text-[13px] text-text-2">#{page.id}</span>
					</Prop>
				</div>
			)}
		</section>
	);
};

interface PropProps {
	label: string;
	children: React.ReactNode;
	span?: 1 | 2;
}

const Prop = ({ label, children, span = 1 }: PropProps) => (
	<div className={`flex min-w-0 flex-col gap-1 ${span === 2 ? "md:col-span-2" : ""}`}>
		<SectionLabel size="xs">{label}</SectionLabel>
		{children}
	</div>
);

const SeoRatingValue = ({ query }: { query: UseQueryResult<PageSeoRating> }) => {
	if (query.isLoading) {
		return <span className="text-[14px] font-medium text-text-3">…</span>;
	}

	const data = query.data;

	if (query.isError || !data || !data.available || data.score === null) {
		return (
			<UnknownValue title="This page can't be rated yet (external link or no template)." />
		);
	}

	const tip =
		data.recommendations.length > 0
			? `SEO goals:\n• ${data.recommendations.join("\n• ")}`
			: "You meet all recommended SEO goals.";

	return (
		<span
			className="inline-flex items-center gap-1.5 text-[14px] font-medium"
			style={{ color: data.color ?? undefined }}
		>
			{data.score}%
			<IconButton label={tip} size="sm" title={tip}>
				<HelpCircle size={11} />
			</IconButton>
		</span>
	);
};

const UnknownValue = ({ title }: { title: string }) => (
	<span className="inline-flex items-center gap-1.5 text-[14px] font-medium text-text-3">
		—
		<IconButton label={title} size="sm" title={title}>
			<HelpCircle size={11} />
		</IconButton>
	</span>
);
