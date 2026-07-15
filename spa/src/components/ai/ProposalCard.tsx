import { useNavigate } from "react-router-dom";
import { Check, CircleCheck, CircleSlash, Clock, FilePlus2, X } from "lucide-react";

import type { ChatProposal } from "@/api/endpoints/ai";
import { pageEditPath } from "@/lib/routes";
import { Button } from "@/components/ui/Button";

/**
 * A staged mutation the assistant proposed. Nothing has changed yet: the user
 * reviews the summary + preview and approves or rejects it. Once resolved the card
 * shows the outcome (and, for a page, a deep link).
 *
 * The AI never executes — approval hits the server, which re-checks permission and
 * runs the change from the stored payload. The preview shape varies by tool, so the
 * body is rendered generically (a field diff, a field list, or plain key/values).
 */

interface ProposalCardProps {
	proposal: ChatProposal;
	onApprove: (id: string) => void;
	onReject: (id: string) => void;
	/** Approve/reject request in flight for this card. */
	busy?: boolean;
	/** Close the chat panel after following a link to a created entity. */
	onNavigate: () => void;
}

/** Nicer labels for keys we render often; anything else is humanized from snake_case. */
const KEY_LABELS: Record<string, string> = {
	nav_title: "Nav title",
	title: "Title",
	page_title: "Page",
	user_label: "User",
	template: "Template",
	parent_title: "Location",
	display_field: "Display field",
	meta_description: "Meta description",
	meta_keywords: "Meta keywords",
	in_nav: "In navigation",
	seo_invisible: "Hidden from search",
};

/** Preview keys that are structural/noise and never rendered as a plain row. */
const HIDDEN_KEYS = new Set([
	"action",
	"changes",
	"fields",
	"tags",
	"new_tags",
	"existing_tags",
	"mode",
	"page_id",
	"entry_id",
	"change_id",
	"user_id",
]);

const humanize = (key: string): string =>
	KEY_LABELS[key] ?? key.replace(/_/g, " ").replace(/^\w/, (c) => c.toUpperCase());

const displayValue = (value: unknown): string => {
	if (value === true) {
		return "Yes";
	}

	if (value === false) {
		return "No";
	}

	if (value == null || value === "") {
		return "—";
	}

	if (Array.isArray(value)) {
		return value.map((v) => String(v)).join(", ");
	}

	if (typeof value === "object") {
		return JSON.stringify(value);
	}

	return String(value);
};

type Row = { label: string; from?: string; to: string };

/** A {from,to} pair as emitted by *_page / *_user / update_template previews. */
const isDiff = (v: unknown): v is { from?: unknown; to: unknown } =>
	typeof v === "object" && v !== null && "to" in (v as Record<string, unknown>);

/**
 * Flatten a heterogeneous preview into display rows. Handles three shapes the
 * backends emit — a `changes` diff map, a `fields` list, and plain scalar keys —
 * plus a tag list, without the card needing to know which tool produced it.
 */
const previewRows = (preview: Record<string, unknown>): Row[] => {
	const rows: Row[] = [];

	const changes = preview.changes;

	if (changes && typeof changes === "object" && !Array.isArray(changes)) {
		for (const [key, value] of Object.entries(changes as Record<string, unknown>)) {
			if (isDiff(value)) {
				rows.push({
					label: humanize(key),
					from: displayValue(value.from),
					to: displayValue(value.to),
				});
			} else {
				rows.push({ label: humanize(key), to: displayValue(value) });
			}
		}
	}

	if (Array.isArray(preview.fields)) {
		for (const field of preview.fields as Array<Record<string, unknown>>) {
			const label = String(field.title || field.column || field.id || "Field");

			if ("to" in field) {
				rows.push({
					label,
					from: "from" in field ? displayValue(field.from) : undefined,
					to: displayValue(field.to),
				});
			} else {
				rows.push({ label, to: displayValue(field.type ?? "") });
			}
		}
	}

	if (Array.isArray(preview.tags)) {
		rows.push({ label: "Tags", to: displayValue(preview.tags) });
	}

	for (const [key, value] of Object.entries(preview)) {
		if (
			HIDDEN_KEYS.has(key) ||
			isDiff(value) ||
			Array.isArray(value) ||
			typeof value === "object"
		) {
			continue;
		}

		rows.push({ label: humanize(key), to: displayValue(value) });
	}

	return rows;
};

const STATUS_META: Record<
	ChatProposal["status"],
	{ icon: typeof CircleCheck; label: string; className: string }
> = {
	pending: { icon: Clock, label: "Awaiting your approval", className: "text-text-3" },
	approved: { icon: CircleCheck, label: "Approved", className: "text-success" },
	rejected: { icon: CircleSlash, label: "Rejected", className: "text-text-3" },
	expired: { icon: CircleSlash, label: "Expired", className: "text-warn" },
};

/** Post-approval one-liner describing what actually happened server-side. */
const outcomeText = (result: Record<string, unknown> | null): string => {
	if (!result) {
		return "";
	}

	switch (result.mode) {
		case "published":
			return "Published live.";
		case "pending":
			return "Queued as a pending change for a publisher to review.";
		case "archived":
			return "Page archived.";
		case "tagged":
			return "Tags added.";
		case "created":
			return "Created.";
		case "updated":
			return "Updated.";
		case "error":
			return String(result.message ?? "That change could no longer be applied.");
		default:
			return "Done.";
	}
};

export const ProposalCard = ({
	proposal,
	onApprove,
	onReject,
	busy,
	onNavigate,
}: ProposalCardProps) => {
	const navigate = useNavigate();
	const status = STATUS_META[proposal.status] ?? STATUS_META.pending;
	const StatusIcon = status.icon;
	const isPending = proposal.status === "pending";

	const rows = previewRows(proposal.preview);

	// Deep link to a page the approval touched (created or edited), when we have its id.
	const pageId =
		proposal.status === "approved" && typeof proposal.result?.page_id === "number"
			? (proposal.result.page_id as number)
			: undefined;

	return (
		<div className="mt-2 overflow-hidden rounded-xl border border-accent/30 bg-surface-2/40">
			<div className="flex items-center gap-2 border-b border-border/70 px-3 py-2">
				<FilePlus2 size={14} className="shrink-0 text-accent" />
				<span className="flex-1 text-[12px] font-semibold text-text">Proposed change</span>
				<span className={`flex items-center gap-1 text-[11px] ${status.className}`}>
					<StatusIcon size={12} />
					{status.label}
				</span>
			</div>

			<div className="px-3 py-2.5">
				<p className="text-[12.5px] leading-relaxed text-text-2">{proposal.summary}</p>

				{rows.length > 0 && (
					<dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
						{rows.map((row, i) => (
							<div key={`${row.label}-${i}`} className="contents">
								<dt className="text-[11px] text-text-3">{row.label}</dt>
								<dd className="truncate text-[11.5px] text-text">
									{row.from !== undefined ? (
										<>
											<span className="text-text-3 line-through">
												{row.from}
											</span>{" "}
											<span aria-hidden>→</span> {row.to}
										</>
									) : (
										row.to
									)}
								</dd>
							</div>
						))}
					</dl>
				)}

				{proposal.status === "approved" && (
					<p className="mt-2 text-[11.5px] text-success">
						{outcomeText(proposal.result)}
					</p>
				)}

				{isPending && (
					<div className="mt-3 flex items-center gap-2">
						<Button
							size="sm"
							variant="primary"
							icon={<Check size={14} />}
							loading={busy}
							loadingLabel="Working…"
							onClick={() => onApprove(proposal.proposal_id)}
						>
							Approve
						</Button>
						<Button
							size="sm"
							variant="secondary"
							icon={<X size={14} />}
							disabled={busy}
							onClick={() => onReject(proposal.proposal_id)}
						>
							Reject
						</Button>
					</div>
				)}

				{pageId !== undefined && (
					<Button
						size="sm"
						variant="link"
						className="mt-2 -ml-1"
						onClick={() => {
							navigate(pageEditPath(pageId));
							onNavigate();
						}}
					>
						View page
					</Button>
				)}
			</div>
		</div>
	);
};
