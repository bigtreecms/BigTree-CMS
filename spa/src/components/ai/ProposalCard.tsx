import { useNavigate } from "react-router-dom";
import { Check, CircleCheck, CircleSlash, Clock, FilePlus2, TriangleAlert, X } from "lucide-react";

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
	/** Approve/reject request in flight for this card. */
	busy?: boolean;
	onApprove: (id: string) => void;
	/** Close the chat panel after following a link to a created entity. */
	onNavigate: () => void;
	onReject: (id: string) => void;
	proposal: ChatProposal;
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
	// `tags` minus `new_tags`: the two rows below already say which of the requested
	// tags are new, so a third row listing the rest is noise.
	"existing_tags",
	"mode",
	"page_id",
	"entry_id",
	"change_id",
	"user_id",
	// Rendered as their own affordances (a warning banner) or already implied by
	// the summary, rather than as anonymous "Key: true" rows.
	"destructive",
	"target",
	"target_title",
	"module_id",
	"mine",
	"is_new_item",
	"template_changed",
	"sends_invite_email",
	"grants_permissions",
	"note",
	// Rendered as dedicated blocks below, not as anonymous key/value rows.
	"warning",
	"remaining_setup",
	"ignored",
	"publishes_draft",
	"content_lock",
	"incomplete_required",
	"depends_on",
	"unsettable_columns",
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

	// Which of those tags don't exist yet. The backends have always emitted this
	// ("surfaced in the preview so the user sees exactly what a new tag would
	// introduce") and the card hid the key, so the approver saw a count in the
	// summary and never the names — while coining a tag is administrator-gated
	// precisely because it grows the site's shared vocabulary, and the approver is
	// the person who would catch "Press Releases" beside the existing "Press
	// Release".
	if (Array.isArray(preview.new_tags) && preview.new_tags.length > 0) {
		rows.push({ label: "New tags", to: displayValue(preview.new_tags) });
	}

	// merge_tags names the tags about to be destroyed in a top-level `from` array —
	// the single most important thing on that card, and it had no renderer at all,
	// so an irreversible merge was approved without naming what it consumed.
	if (Array.isArray(preview.from)) {
		rows.push({ label: "Merging", to: displayValue(preview.from) });
	}

	if (typeof preview.into === "string" && preview.into !== "") {
		rows.push({ label: "Into", to: preview.into });
	}

	// A top-level from/to pair — update_setting and set_module_entry_flag emit the
	// two keys side by side rather than nested under `changes`, so the generic scan
	// below rendered them as two unrelated rows ("From: on", "To: ") instead of one
	// before/after.
	const hasTopLevelDiff = "to" in preview && !Array.isArray(preview.to);

	if (hasTopLevelDiff) {
		rows.push({
			label:
				typeof preview.field === "string" && preview.field !== ""
					? humanize(preview.field)
					: "Value",
			from:
				"from" in preview && !Array.isArray(preview.from)
					? displayValue(preview.from)
					: undefined,
			to: displayValue(preview.to),
		});
	}

	for (const [key, value] of Object.entries(preview)) {
		if (
			HIDDEN_KEYS.has(key) ||
			isDiff(value) ||
			Array.isArray(value) ||
			typeof value === "object" ||
			(hasTopLevelDiff && (key === "from" || key === "to" || key === "field"))
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
	// An approval that ran and refused. Deliberately not a success badge: the
	// change did not happen, nothing was audited, and the card stays approvable.
	failed: { icon: TriangleAlert, label: "Not applied", className: "text-warn" },
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
		case "unarchived":
			return "Page restored.";
		case "moved":
			return result.path ? `Moved to ${String(result.path)}.` : "Page moved.";
		case "deleted":
			return "Deleted.";
		case "rejected":
			return "Pending change rejected.";
		case "tagged":
			return "Tags added.";
		case "untagged":
			return "Tags removed.";
		case "created":
			return "Created.";
		case "updated":
			return "Updated.";
		case "restored":
			return "Restored.";
		case "merged":
			return result.tag ? `Merged into “${String(result.tag)}”.` : "Tags merged.";
		case "renamed":
			return result.tag ? `Renamed to “${String(result.tag)}”.` : "Renamed.";
		case "saved":
			return "Saved.";
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
	// A failed approval keeps its buttons: the server leaves it claimable so the
	// user can fix the cause (or just try again) rather than being stranded.
	const isPending = proposal.status === "pending" || proposal.status === "failed";

	const rows = previewRows(proposal.preview);

	// Deletions and rejections flag themselves; the required-field list names what
	// the assistant could not fill. Both are warnings, not table rows.
	const isDestructive = proposal.preview.destructive === true;
	const incompleteRequired = Array.isArray(proposal.preview.incomplete_required)
		? (proposal.preview.incomplete_required as unknown[]).map(String)
		: [];

	// Keys that carry a consequence rather than a value. Rendered as their own
	// blocks: as plain preview rows they were single-line truncated inside a 440px
	// panel, which clipped exactly the part that mattered — or, for the ones with no
	// renderer at all, dropped silently.
	const warning = typeof proposal.preview.warning === "string" ? proposal.preview.warning : "";
	const publishesDraft =
		typeof (proposal.preview.publishes_draft as Record<string, unknown> | undefined)?.note ===
		"string"
			? String((proposal.preview.publishes_draft as Record<string, unknown>).note)
			: null;
	const lockValue = proposal.preview.content_lock as Record<string, unknown> | undefined;
	const contentLock =
		lockValue && typeof lockValue.holder === "string"
			? { holder: lockValue.holder, kind: String(lockValue.kind ?? "item") }
			: null;
	// This card refers to something another, still-unapproved card would create.
	// Approving them out of order is refused server-side, so the order has to be
	// visible here rather than discovered by clicking.
	const dependsOn =
		typeof proposal.preview.depends_on === "string" ? proposal.preview.depends_on : "";
	const ignored = Array.isArray(proposal.preview.ignored)
		? (proposal.preview.ignored as unknown[]).map(String)
		: [];
	const remainingSetup = Array.isArray(proposal.preview.remaining_setup)
		? (proposal.preview.remaining_setup as unknown[]).map(String)
		: [];
	// Columns the table requires that the module's form can't fill, so the write
	// stores them empty — the same disclosure as `incomplete_required`, for the
	// columns nobody can fill rather than the ones the assistant couldn't.
	const unsettableColumns = Array.isArray(proposal.preview.unsettable_columns)
		? (proposal.preview.unsettable_columns as unknown[]).map(String)
		: [];

	// Deep link to a page the approval touched (created or edited), when we have its id.
	const pageId =
		proposal.status === "approved" && typeof proposal.result?.page_id === "number"
			? (proposal.result.page_id as number)
			: undefined;

	return (
		<div className="mt-2 overflow-hidden rounded-xl border border-accent/30 bg-surface-2/40">
			<div className="flex items-center gap-2 border-b border-border/70 px-3 py-2">
				<FilePlus2 className="shrink-0 text-accent" size={14} />
				<span className="flex-1 text-[12px] font-semibold text-text">Proposed change</span>
				<span className={`flex items-center gap-1 text-[11px] ${status.className}`}>
					<StatusIcon size={12} />
					{status.label}
				</span>
			</div>

			<div className="px-3 py-2.5">
				<p className="text-[12.5px] leading-relaxed text-text-2">{proposal.summary}</p>

				{isDestructive && (
					<p className="mt-2 flex items-start gap-1.5 text-[11.5px] text-warn">
						<TriangleAlert className="mt-px shrink-0" size={13} />
						<span>This permanently deletes content and cannot be undone.</span>
					</p>
				)}

				{incompleteRequired.length > 0 && (
					<p className="mt-2 flex items-start gap-1.5 text-[11.5px] text-warn">
						<TriangleAlert className="mt-px shrink-0" size={13} />
						<span>
							Needs a person to fill in afterwards: {incompleteRequired.join(", ")}
						</span>
					</p>
				)}

				{warning !== "" && (
					<p className="mt-2 flex items-start gap-1.5 whitespace-pre-wrap wrap-break-word text-[11.5px] text-warn">
						<TriangleAlert className="mt-px shrink-0" size={13} />
						<span>{warning}</span>
					</p>
				)}

				{publishesDraft !== null && (
					<p className="mt-2 flex items-start gap-1.5 whitespace-pre-wrap wrap-break-word text-[11.5px] text-warn">
						<TriangleAlert className="mt-px shrink-0" size={13} />
						<span>{publishesDraft}</span>
					</p>
				)}

				{contentLock !== null && (
					<p className="mt-2 flex items-start gap-1.5 whitespace-pre-wrap wrap-break-word text-[11.5px] text-warn">
						<TriangleAlert className="mt-px shrink-0" size={13} />
						<span>
							{contentLock.holder} has this {contentLock.kind} open in the editor
							right now — if they save after this is approved, their copy wins.
						</span>
					</p>
				)}

				{dependsOn !== "" && (
					<p className="mt-2 flex items-start gap-1.5 whitespace-pre-wrap wrap-break-word text-[11.5px] text-warn">
						<Clock className="mt-px shrink-0" size={13} />
						<span>{dependsOn}</span>
					</p>
				)}

				{unsettableColumns.length > 0 && (
					<p className="mt-2 text-[11.5px] text-text-3">
						Stored empty (no form field fills them): {unsettableColumns.join(", ")}
					</p>
				)}

				{ignored.length > 0 && (
					<p className="mt-2 text-[11.5px] text-text-3">
						Ignored (not applicable): {ignored.join(", ")}
					</p>
				)}

				{remainingSetup.length > 0 && (
					<div className="mt-2">
						<p className="text-[11px] text-text-3">Still to do by hand:</p>
						<ul className="mt-1 list-disc space-y-0.5 pl-4 text-[11.5px] text-text-2">
							{remainingSetup.map((step, i) => (
								<li key={i}>{step}</li>
							))}
						</ul>
					</div>
				)}

				{rows.length > 0 && (
					<dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
						{rows.map((row, i) => (
							<div className="contents" key={`${row.label}-${i}`}>
								<dt className="text-[11px] text-text-3">{row.label}</dt>
								<dd className="max-h-40 overflow-y-auto whitespace-pre-wrap wrap-break-word text-[11.5px] text-text">
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

				{proposal.status === "failed" && (
					<p className="mt-2 flex items-start gap-1.5 text-[11.5px] text-warn">
						<TriangleAlert className="mt-px shrink-0" size={13} />
						<span>{outcomeText(proposal.result)}</span>
					</p>
				)}

				{isPending && (
					<div className="mt-3 flex items-center gap-2">
						<Button
							icon={<Check size={14} />}
							loading={busy}
							loadingLabel="Working…"
							size="sm"
							variant="primary"
							onClick={() => onApprove(proposal.proposal_id)}
						>
							Approve
						</Button>
						<Button
							disabled={busy}
							icon={<X size={14} />}
							size="sm"
							variant="secondary"
							onClick={() => onReject(proposal.proposal_id)}
						>
							Reject
						</Button>
					</div>
				)}

				{pageId !== undefined && (
					<Button
						className="mt-2 -ml-1"
						size="sm"
						variant="link"
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
