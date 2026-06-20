import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Navigate, useLocation, useNavigate, useParams } from "react-router-dom";
import { Calendar, ChevronLeft, Save } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Button } from "@/components/ui/Button";
import { LockBanner } from "@/components/ui/LockBanner";

import { AccessLevelsDialog } from "@/components/pages/AccessLevelsDialog";
import { LinkFinder } from "@/components/pages/LinkFinder";
import { MovePageDialog } from "@/components/pages/MovePageDialog";
import { PageSectionToolbar } from "@/components/pages/PageSectionToolbar";
import { PageSummaryPanel } from "@/components/pages/PageSummaryPanel";

import {
	isPendingResult,
	pagesApi,
	type PageDetail,
	type PageEditBody,
} from "@/api/endpoints/pages";
import { pendingChangesApi } from "@/api/endpoints/dashboard";
import { TagInput } from "@/components/tags/TagInput";
import type { Tag } from "@/api/endpoints/tags";
import { resourceToFormField, templatesApi, type TemplateSummary } from "@/api/endpoints/templates";

import { FieldRenderer } from "@/renderer/forms/FieldRenderer";
import { FieldRow } from "@/renderer/forms/FieldRow";
import { isFieldRequired, isFieldValueEmpty } from "@/renderer/forms/validation";
import { PendingBadge } from "@/components/pending-changes/PendingBadge";
import { PendingFieldCompare } from "@/components/pending-changes/PendingFieldCompare";
import { draftOwnerLabel, fieldValuesEqual } from "@/lib/fieldComparison";
import { useAuthStore } from "@/auth/store";

import { useLock } from "@/hooks/useLock";
import { useScrollToFirstError } from "@/hooks/useScrollToFirstError";
import { useDirtyTracker } from "@/hooks/useDirtyTracker";
import { UnsavedChangesGuard } from "@/components/ui/UnsavedChangesGuard";
import { ApiError } from "@/types/api";
import { canPublishPage } from "@/lib/permissions";
import { toast } from "@/lib/toast";

/**
 * Page editor — four tabs (Properties / Content / SEO / Sharing) above a
 * dynamic template-driven Content area. Layout is ported from the
 * `add-subpage-screen.jsx` reference design but reused unchanged for edit so
 * the two flows stay visually consistent.
 *
 * On submit, a single PATCH bundles every dirty field across all tabs. Field
 * 422 errors are routed by `column` and shown under the corresponding input.
 */

type TabValue = "properties" | "content" | "seo" | "sharing";

const PAGE_TABS: TabValue[] = ["properties", "content", "seo", "sharing"];

/**
 * Resolves per-field pending-change state for the page editor's field wrappers.
 * Present only when editing a live page that has a queued EDIT overlaid; a field
 * is "pending" when its column appears in the change's `changed_fields`.
 */
export interface PendingFieldInfo {
	isPending: (column: string) => boolean;
	published: (column: string) => unknown;
	current: (column: string) => unknown;
	/** Heading for the draft column, attributed to the change's owner. */
	label: string;
}

const seedBody = (page: PageDetail): PageEditBody => ({
	nav_title: page.nav_title,
	title: page.title,
	route: page.route,
	in_nav: page.in_nav,
	template: page.template,
	external: page.external,
	new_window: page.new_window,
	meta_keywords: page.meta_keywords,
	meta_description: page.meta_description,
	seo_invisible: page.seo_invisible,
	publish_at: page.publish_at,
	expire_at: page.expire_at,
	max_age: page.max_age,
	trunk: page.trunk,
	resources: (page.resources ?? {}) as Record<string, unknown>,
	open_graph: page.open_graph ? { ...page.open_graph } : undefined,
	tags: (page.tags ?? []).map((t) => t.id),
});

export const PageEdit = () => {
	const { id: idParam, pcid: pcidParam } = useParams<{ id?: string; pcid?: string }>();
	// Two mount points share this component: /pages/:id/edit (a live page, whose
	// queued EDIT draft is overlaid on load) and /pages/draft/:pcid/edit (a NEW
	// page that only exists in bigtree_pending_changes).
	const draft = pcidParam !== undefined;
	const id = Number(idParam);
	const pcid = Number(pcidParam);
	const navigate = useNavigate();
	const location = useLocation();
	const queryClient = useQueryClient();

	const valid = draft ? Number.isFinite(pcid) && pcid > 0 : Number.isFinite(id) && id > 0;

	// Where to send the editor after a save/delete. Mirrors the legacy admin,
	// which never leaves you on the edit screen with just a growl: it returns to
	// the tree view you came from (the page's parent), or to whatever screen
	// linked here (captured in router state as `from`).
	const returnTo = (parent: number | undefined): string => {
		const from = (location.state as { from?: string } | null)?.from;

		if (from) {
			return from;
		}

		return parent && parent > 0 ? `/pages/${parent}` : "/pages";
	};

	const pageQuery = useQuery({
		queryKey: draft
			? ["pages", "draft", pcid]
			: ["pages", "detail", id, { lineage: true, pending: true }],
		queryFn: () =>
			draft
				? pagesApi.getPending(pcid, { lineage: true })
				: pagesApi.get(id, { lineage: true, pending: true }),
		enabled: valid,
	});

	const templatesQuery = useQuery({
		queryKey: ["templates", "list"],
		queryFn: () => templatesApi.list(),
	});

	const templateId = pageQuery.data?.template;
	const templateQuery = useQuery({
		queryKey: ["templates", "detail", templateId],
		queryFn: () => templatesApi.get(templateId as string),
		enabled: Boolean(templateId),
	});

	// NEW drafts have no live row to lock; only live pages take an edit lock.
	const lock = useLock({
		table: "bigtree_pages",
		itemId: draft ? 0 : id,
		title: pageQuery.data?.nav_title,
		enabled: !draft && valid && Boolean(pageQuery.data),
	});

	const [body, setBody] = useState<PageEditBody | null>(null);
	// Full Tag objects for the browser chips; body.tags carries just the ids.
	const [tagObjects, setTagObjects] = useState<Tag[]>([]);
	const [activeTab, setActiveTab] = useState<TabValue>("content");
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
	const [generalError, setGeneralError] = useState<string | null>(null);
	const [confirmDelete, setConfirmDelete] = useState(false);
	const [movingOpen, setMovingOpen] = useState(false);
	const [accessOpen, setAccessOpen] = useState(false);

	useScrollToFirstError(fieldErrors);

	useEffect(() => {
		if (pageQuery.data) {
			setBody(seedBody(pageQuery.data));
			setTagObjects(pageQuery.data.tags ?? []);
			setFieldErrors({});
			setGeneralError(null);
		}
	}, [pageQuery.data]);

	const saveMutation = useMutation({
		mutationFn: (next: PageEditBody) =>
			draft ? pagesApi.patchPending(pcid, next) : pagesApi.patch(id, next),
		onSuccess: (updated) => {
			queryClient.invalidateQueries({ queryKey: ["pages", "list"] });
			// The live-publish response omits `access`/lineage, so invalidate rather
			// than seeding the cache with a partial detail.
			queryClient.invalidateQueries({ queryKey: ["pages", "detail", id] });

			if (draft) {
				queryClient.invalidateQueries({ queryKey: ["pages", "draft", pcid] });
			}

			if (isPendingResult(updated)) {
				toast.success("Draft saved", {
					description: "Your change is pending publisher approval.",
				});
			} else {
				toast.success("Page published");
			}

			navigate(returnTo(pageQuery.data?.parent));
		},
		onError: (err) => {
			if (err instanceof ApiError) {
				const fe = err.fieldErrors();

				if (Object.keys(fe).length > 0) {
					setFieldErrors(fe);
				}

				setGeneralError(err.message);
			} else {
				setGeneralError(err instanceof Error ? err.message : "Save failed");
			}
		},
	});

	const duplicateMutation = useMutation({
		mutationFn: () => pagesApi.duplicate(id),
		onSuccess: (result) => {
			queryClient.invalidateQueries({ queryKey: ["pages", "list"] });
			toast.success("Page duplicated", {
				description: "The copy was created as an unpublished draft.",
			});
			navigate(`/pages/draft/${result.pending_change_id}/edit`);
		},
		onError: (err) => {
			toast.error(
				err instanceof ApiError && err.message ? err.message : "Could not duplicate page"
			);
		},
	});

	const deleteMutation = useMutation({
		// A NEW draft only exists in bigtree_pending_changes, so "deleting" it means
		// rejecting the pending change (mirrors the tree's draft-delete action).
		mutationFn: () => (draft ? pendingChangesApi.reject(pcid) : pagesApi.delete(id)),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["pages", "list"] });
			toast.success(draft ? "Draft deleted" : "Page deleted");
			navigate(returnTo(pageQuery.data?.parent));
		},
		onError: () => {
			toast.error(draft ? "Could not delete draft" : "Could not delete page");
		},
	});

	const setBodyPatch = (patch: Partial<PageEditBody>) => {
		setBody((prev) => (prev ? { ...prev, ...patch } : prev));
	};

	// Per-field pending markers apply only when editing a live page with a queued
	// EDIT overlaid (NEW drafts are wholly unpublished — the banner covers that).
	const pageData = pageQuery.data;
	const currentUserId = useAuthStore((s) => s.user?.id);
	const isAdminUser = useAuthStore((s) => (s.user?.level ?? 0) >= 1);
	const pendingInfo = useMemo<PendingFieldInfo | undefined>(() => {
		if (draft || !pageData?.changes_applied) {
			return undefined;
		}

		const changed = new Set(pageData.changed_fields ?? []);
		const original = pageData.pending_original ?? {};

		return {
			isPending: (column) => changed.has(column),
			published: (column) => original[column],
			current: (column) => (body as Record<string, unknown> | null)?.[column],
			label: draftOwnerLabel(
				pageData.pending_owner,
				pageData.pending_owner_name,
				currentUserId
			),
		};
	}, [draft, pageData, body, currentUserId]);

	// Which template resources carry a queued change, computed ONCE from the
	// loaded data (published vs. the overlaid draft). Deliberately not derived
	// from the live `body` — otherwise simply typing into a field would flip it
	// to "pending" before saving (and toggling that flag collapses callouts).
	const changedResourceIds = useMemo<Set<string> | null>(() => {
		if (!pendingInfo || !pageData) {
			return null;
		}

		const published = (pageData.pending_original?.resources ?? {}) as Record<string, unknown>;
		const loadedDraft = (pageData.resources ?? {}) as Record<string, unknown>;
		const ids = new Set<string>();

		for (const key of new Set([...Object.keys(published), ...Object.keys(loadedDraft)])) {
			if (!fieldValuesEqual(published[key], loadedDraft[key])) {
				ids.add(key);
			}
		}

		return ids;
		// pendingInfo already keys off pageData; depend on pageData for the data.
	}, [pendingInfo, pageData]);

	const handleSave = (publish: boolean) => {
		if (!body || saveMutation.isPending || lock.ownedByOther) {
			return;
		}

		const resourceValues = (body.resources ?? {}) as Record<string, unknown>;
		const resourceErrors: Record<string, string> = {};

		for (const resource of templateQuery.data?.resources ?? []) {
			const field = resourceToFormField(resource);

			if (isFieldRequired(field) && isFieldValueEmpty(field, resourceValues[field.column])) {
				resourceErrors[field.column] = `${field.title || field.column} is required.`;
			}
		}

		if (Object.keys(resourceErrors).length > 0) {
			setFieldErrors(resourceErrors);
			setGeneralError("Please fill in the required fields.");
			setActiveTab("content");

			return;
		}

		setGeneralError(null);
		setFieldErrors({});
		saveMutation.mutate({ ...body, publish });
	};

	// Dirty once the page has loaded and been edited; suppressed while a save or
	// delete is in flight (both navigate away) and when the row is read-only
	// because another user holds the lock.
	const isDirty =
		useDirtyTracker(body, body !== null) &&
		!saveMutation.isPending &&
		!deleteMutation.isPending &&
		!lock.ownedByOther;

	if (!valid) {
		return <Navigate to="/pages" replace />;
	}

	if (pageQuery.isLoading || !pageQuery.data || !body) {
		return (
			<div className="mx-auto max-w-screen-2xl px-6 py-4">
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading page…
				</div>
			</div>
		);
	}

	if (pageQuery.error) {
		return (
			<div className="mx-auto max-w-screen-2xl px-6 py-4">
				<ErrorPanel error={pageQuery.error} />
			</div>
		);
	}

	const page = pageQuery.data;
	const readOnly = lock.ownedByOther;
	const canPublish = canPublishPage(page.access);
	const lineage = page.lineage ?? [];

	const breadcrumbs = [
		{ label: "Pages", to: "/pages" },
		...lineage.map((p) => ({ label: p.nav_title, to: `/pages/${p.id}` })),
		{ label: draft ? "Edit draft" : "Edit" },
	];

	const templateDisabled = Boolean(body.external && body.external.trim().length > 0);

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={breadcrumbs} />

			<PageHead
				title={page.nav_title || "Untitled page"}
				sub="Edit the page's properties, content, SEO, and sharing metadata."
				actions={
					<Button
						variant="danger"
						onClick={() => setConfirmDelete(true)}
						disabled={readOnly}
					>
						Delete
					</Button>
				}
			/>

			<PageSummaryPanel page={page} />

			<PageSectionToolbar
				active="edit"
				pageId={page.id}
				parentId={page.parent}
				onMove={draft ? undefined : () => setMovingOpen(true)}
				onDuplicate={
					// Live, non-top-level pages only (drafts have nothing to copy;
					// legacy refuses top-level pages). The server enforces publisher
					// access on the page + parent.
					draft || page.parent < 1 || duplicateMutation.isPending
						? undefined
						: () => duplicateMutation.mutate()
				}
				onAccessLevels={
					// Admin-only viewer (the endpoint enforces it too); drafts have
					// no live page to inspect.
					!draft && isAdminUser ? () => setAccessOpen(true) : undefined
				}
			/>

			{page.changes_applied && (
				<div className="mb-3 rounded-md border border-warn/40 bg-warn/5 px-3 py-2 text-[12.5px] text-text-2">
					{draft
						? "This page is an unpublished draft and isn’t live yet. "
						: "You’re editing unpublished draft changes — the live page still shows the previously published content. "}
					{canPublish
						? "“Save” keeps it as a draft; “Save & Publish” makes it live."
						: "“Save” updates the draft for a publisher to review and publish."}
				</div>
			)}

			{readOnly && (
				<LockBanner
					owner={lock.lockOwner}
					lockedAt={lock.lockedAt}
					onUnlock={lock.forceUnlock}
				/>
			)}

			{generalError && (
				<div className="mb-3 rounded-md border border-danger/40 bg-danger/5 px-3 py-2 text-[12.5px] text-danger">
					{generalError}
				</div>
			)}

			<form
				onSubmit={(e) => {
					e.preventDefault();
					handleSave(false);
				}}
				className="mb-6 overflow-hidden rounded-lg border border-border bg-surface"
			>
				<TabBar value={activeTab} onChange={setActiveTab} />

				<div className="flex flex-col gap-[18px] p-[22px]">
					{activeTab === "properties" && (
						<PropertiesTab
							body={body}
							templates={templatesQuery.data ?? []}
							templateDisabled={templateDisabled}
							fieldErrors={fieldErrors}
							disabled={readOnly}
							onPatch={setBodyPatch}
							pending={pendingInfo}
						/>
					)}

					{activeTab === "content" && (
						<ContentTab
							body={body}
							template={templateDisabled ? undefined : templateQuery.data}
							loading={!templateDisabled && templateQuery.isLoading}
							templateDisabled={templateDisabled}
							fieldErrors={fieldErrors}
							disabled={readOnly}
							onChange={(resources) => setBodyPatch({ resources })}
							tags={tagObjects}
							onTagsChange={(next) => {
								setTagObjects(next);
								setBodyPatch({ tags: next.map((t) => t.id) });
							}}
							publishedResources={
								pendingInfo
									? ((page.pending_original?.resources as
											| Record<string, unknown>
											| undefined) ?? {})
									: undefined
							}
							changedResourceIds={changedResourceIds}
							pendingLabel={pendingInfo?.label}
						/>
					)}

					{activeTab === "seo" && (
						<SeoTab
							body={body}
							page={page}
							fieldErrors={fieldErrors}
							disabled={readOnly}
							onPatch={setBodyPatch}
							pending={pendingInfo}
						/>
					)}

					{activeTab === "sharing" && (
						<SharingTab
							body={body}
							disabled={readOnly}
							onPatch={setBodyPatch}
							pending={pendingInfo}
						/>
					)}
				</div>

				<WizardFooter
					activeTab={activeTab}
					onSelect={setActiveTab}
					primaryLabel={saveMutation.isPending ? "Saving…" : "Save"}
					onPrimary={() => handleSave(false)}
					primaryDisabled={readOnly || saveMutation.isPending}
					publishLabel={canPublish ? "Save & Publish" : undefined}
					onPublish={canPublish ? () => handleSave(true) : undefined}
					publishDisabled={readOnly || saveMutation.isPending}
				/>
			</form>

			{confirmDelete && (
				<ConfirmDialog
					open={true}
					onOpenChange={setConfirmDelete}
					title={
						draft
							? `Discard draft “${page.nav_title || "Untitled"}”?`
							: `Delete “${page.nav_title}”?`
					}
					description={
						draft
							? "This permanently discards the unpublished draft. The page was never published, so nothing else is affected."
							: "This removes the page and all of its descendants. The action cannot be undone."
					}
					confirmLabel={draft ? "Discard draft" : "Delete page"}
					variant="danger"
					onConfirm={() => deleteMutation.mutate()}
				/>
			)}

			{!draft && (
				<MovePageDialog
					open={movingOpen}
					onOpenChange={setMovingOpen}
					page={{ id: page.id, nav_title: page.nav_title, parent: page.parent }}
					invalidateKey={["pages", "list", page.parent]}
				/>
			)}

			{!draft && isAdminUser && (
				<AccessLevelsDialog
					open={accessOpen}
					onOpenChange={setAccessOpen}
					pageId={page.id}
				/>
			)}

			<UnsavedChangesGuard isDirty={isDirty} />
		</div>
	);
};

// — Shared sub-blocks (also consumed from PageAdd) —

interface TabBarProps {
	value: TabValue;
	onChange: (next: TabValue) => void;
}

const TAB_LABELS: Record<TabValue, string> = {
	properties: "Properties",
	content: "Content",
	seo: "SEO",
	sharing: "Sharing",
};

export const TabBar = ({ value, onChange }: TabBarProps) => (
	<div className="flex items-stretch gap-0 border-b border-border bg-surface-2 px-4">
		{PAGE_TABS.map((tab) => {
			const active = value === tab;

			return (
				<button
					key={tab}
					type="button"
					onClick={() => onChange(tab)}
					className={`relative whitespace-nowrap px-4 py-3 text-[13px] font-medium transition-colors ${
						active
							? "bg-surface text-text after:absolute after:inset-x-3 after:-bottom-px after:h-0.5 after:rounded after:bg-accent"
							: "text-text-3 hover:text-text"
					}`}
					data-active={active}
				>
					{TAB_LABELS[tab]}
				</button>
			);
		})}
		<div className="flex-1" />
		<LinkFinder />
	</div>
);

interface PropertiesTabProps {
	body: PageEditBody;
	templates: TemplateSummary[];
	templateDisabled: boolean;
	fieldErrors: Record<string, string>;
	disabled?: boolean;
	onPatch: (patch: Partial<PageEditBody>) => void;
	pending?: PendingFieldInfo;
}

export const PropertiesTab = ({
	body,
	templates,
	templateDisabled,
	fieldErrors,
	disabled,
	onPatch,
	pending,
}: PropertiesTabProps) => {
	const flexibleTemplates = templates.filter((t) => !t.routed);
	const specialTemplates = templates.filter((t) => t.routed);

	return (
		<>
			<div className="grid grid-cols-1 gap-[18px] md:grid-cols-2 md:gap-x-[22px]">
				<Field
					label="Navigation Title"
					error={fieldErrors.nav_title}
					column="nav_title"
					pending={pending}
				>
					<input
						className={INPUT}
						value={body.nav_title ?? ""}
						onChange={(e) => onPatch({ nav_title: e.target.value })}
						placeholder="Shown in site nav and breadcrumbs"
						disabled={disabled}
					/>
				</Field>

				<Field
					label="Page Title"
					hint="(web browsers use this for their title bar)"
					error={fieldErrors.title}
					column="title"
					pending={pending}
				>
					<input
						className={INPUT}
						value={body.title ?? ""}
						onChange={(e) => onPatch({ title: e.target.value })}
						placeholder={body.nav_title || "e.g. About us — Your Site"}
						disabled={disabled}
					/>
				</Field>
			</div>

			<div className="grid grid-cols-1 gap-[18px] md:grid-cols-3 md:gap-x-[22px]">
				<Field
					label="Publish At"
					hint="(blank = immediately)"
					column="publish_at"
					pending={pending}
				>
					<DateInput
						value={body.publish_at ?? ""}
						onChange={(v) => onPatch({ publish_at: v || null })}
						disabled={disabled}
					/>
				</Field>
				<Field
					label="Expire At"
					hint="(blank = never)"
					column="expire_at"
					pending={pending}
				>
					<DateInput
						value={body.expire_at ?? ""}
						onChange={(v) => onPatch({ expire_at: v || null })}
						disabled={disabled}
					/>
				</Field>
				<Field
					label="Content Max Age"
					hint="(before alerts)"
					column="max_age"
					pending={pending}
				>
					<select
						className={INPUT}
						value={body.max_age ?? 0}
						onChange={(e) => onPatch({ max_age: Number(e.target.value) })}
						disabled={disabled}
					>
						<option value={0}>No Limit</option>
						<option value={30}>30 days</option>
						<option value={90}>90 days</option>
						<option value={180}>6 months</option>
						<option value={365}>1 year</option>
					</select>
				</Field>
			</div>

			<div className="flex flex-wrap items-center gap-4 rounded-md border border-border bg-surface-2 px-3 py-2">
				<Check
					label="Visible in Navigation"
					checked={Boolean(body.in_nav)}
					onChange={(v) => onPatch({ in_nav: v })}
					disabled={disabled}
					column="in_nav"
					pending={pending}
				/>
				<Check
					label="Trunk"
					checked={Boolean(body.trunk)}
					onChange={(v) => onPatch({ trunk: v })}
					disabled={disabled}
					column="trunk"
					pending={pending}
				/>
			</div>

			<div className="my-1 h-px bg-border" />

			<div className="grid grid-cols-1 gap-[18px] md:grid-cols-2 md:gap-x-[22px]">
				<Field
					label="Template"
					error={fieldErrors.template}
					column="template"
					pending={pending}
				>
					<select
						className={INPUT}
						value={templateDisabled ? "" : (body.template ?? "")}
						onChange={(e) => onPatch({ template: e.target.value })}
						disabled={disabled || templateDisabled}
					>
						{templateDisabled && (
							<option value="">— (overridden by External Link) —</option>
						)}

						{!templateDisabled && (
							<>
								<optgroup label="Flexible Templates">
									{flexibleTemplates.map((t) => (
										<option key={t.id} value={t.id}>
											{t.name}
										</option>
									))}
								</optgroup>

								<optgroup label="Special Templates">
									{specialTemplates.map((t) => (
										<option key={t.id} value={t.id}>
											{t.name}
										</option>
									))}
								</optgroup>
							</>
						)}
					</select>
					{templateDisabled && (
						<span className="mt-1 block text-[11px] text-warn">
							Disabled while External Link is set.
						</span>
					)}
				</Field>

				<Field
					label="External Link"
					hint="(include http://, overrides template)"
					error={fieldErrors.external}
					column="external"
					pending={pending}
				>
					<input
						className={INPUT}
						value={body.external ?? ""}
						onChange={(e) => onPatch({ external: e.target.value })}
						placeholder="https://"
						disabled={disabled}
					/>
					<label className="mt-2 flex items-center gap-2 text-[12.5px] text-text-2">
						<input
							type="checkbox"
							className="h-4 w-4 rounded border-border accent-accent"
							checked={Boolean(body.new_window)}
							onChange={(e) => onPatch({ new_window: e.target.checked })}
							disabled={disabled || !body.external}
						/>
						Open in New Window
					</label>
				</Field>
			</div>
		</>
	);
};

interface ContentTabProps {
	body: PageEditBody;
	template: TemplateSummary | undefined;
	loading: boolean;
	templateDisabled: boolean;
	fieldErrors: Record<string, string>;
	disabled?: boolean;
	onChange: (resources: Record<string, unknown>) => void;
	/** Selected tags. Rendering the tag browser requires onTagsChange too. */
	tags?: Tag[];
	onTagsChange?: (next: Tag[]) => void;
	/**
	 * Published resource values keyed by resource id, for per-field comparison.
	 * Undefined unless the page has a queued EDIT overlaid.
	 */
	publishedResources?: Record<string, unknown>;
	/**
	 * Resource ids that carry a queued change (computed once at load). Drives the
	 * "Pending" marker so it doesn't flip on while the user is still typing.
	 */
	changedResourceIds?: Set<string> | null;
	/** Heading for the draft column in resource comparisons, attributed to its owner. */
	pendingLabel?: string;
}

export const ContentTab = ({
	body,
	template,
	loading,
	templateDisabled,
	fieldErrors,
	disabled,
	onChange,
	tags,
	onTagsChange,
	publishedResources,
	changedResourceIds,
	pendingLabel,
}: ContentTabProps) => {
	const resources = (body.resources ?? {}) as Record<string, unknown>;

	// Tags apply to the page itself (not the template), so the browser renders
	// regardless of which resource branch we land in — mirrors the legacy
	// content tab's always-present tag sidebar.
	const tagsSection = onTagsChange ? (
		<div className="mt-5 border-t border-border pt-4">
			<span className="mb-1.5 block text-[12px] font-medium text-text-2">Tags</span>
			<TagInput
				multiple
				value={tags ?? []}
				onChange={onTagsChange}
				disabled={disabled}
				placeholder="Search for or add tags…"
			/>
		</div>
	) : null;

	if (templateDisabled) {
		return (
			<>
				<TemplateTag label="— External Link —" />
				<NoResources reason="external" />
				{tagsSection}
			</>
		);
	}

	if (loading) {
		return (
			<div className="rounded-md border border-dashed border-border bg-surface-2 p-4 text-center text-[12.5px] text-text-3">
				Loading template…
			</div>
		);
	}

	if (!template) {
		return (
			<>
				<div className="rounded-md border border-dashed border-border bg-surface-2 p-4 text-[12.5px] text-text-3">
					No template assigned. Pick one in the <strong>Properties</strong> tab.
				</div>
				{tagsSection}
			</>
		);
	}

	if (template.resources.length === 0) {
		return (
			<>
				<TemplateTag label={template.name} />
				<NoResources reason="empty" />
				{tagsSection}
			</>
		);
	}

	const setFieldValue = (resourceId: string, next: unknown) => {
		onChange({ ...resources, [resourceId]: next });
	};

	return (
		<>
			<TemplateTag label={template.name} />
			<div className="flex flex-col gap-[18px]">
				{template.resources.map((resource) => {
					const formField = resourceToFormField(resource);
					const publishedValue = publishedResources?.[resource.id];
					const pending = Boolean(changedResourceIds?.has(resource.id));

					return (
						<FieldRow
							key={resource.id}
							field={formField}
							error={fieldErrors[resource.id]}
							pending={pending}
							publishedValue={publishedValue}
							currentValue={resources[resource.id]}
							pendingLabel={pendingLabel}
						>
							<FieldRenderer
								field={formField}
								value={resources[resource.id]}
								onChange={(next) => setFieldValue(resource.id, next)}
								disabled={disabled}
								error={fieldErrors[resource.id]}
							/>
						</FieldRow>
					);
				})}
			</div>
			{tagsSection}
		</>
	);
};

interface SeoTabProps {
	body: PageEditBody;
	page?: PageDetail;
	fieldErrors: Record<string, string>;
	disabled?: boolean;
	onPatch: (patch: Partial<PageEditBody>) => void;
	pending?: PendingFieldInfo;
}

export const SeoTab = ({ body, page, fieldErrors, disabled, onPatch, pending }: SeoTabProps) => {
	const description = body.meta_description ?? "";
	// Parent path → "/foo/bar/" — purely cosmetic so we approximate by stripping
	// the page's own route from page.path. Falls back to "/".
	const parentPath = computeParentPath(page);
	const slugSuggest = (body.nav_title ?? "")
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, "-")
		.replace(/^-|-$/g, "");

	return (
		<>
			<Field
				label="URL Route"
				hint="(leave blank to auto generate)"
				error={fieldErrors.route}
				wide
				column="route"
				pending={pending}
			>
				<div className="flex items-center gap-0 overflow-hidden rounded-md border border-border-strong bg-surface focus-within:border-accent focus-within:ring-1 focus-within:ring-accent-ring">
					<span className="border-r border-border bg-surface-2 px-3 py-[7px] font-mono text-[12.5px] text-text-3">
						{parentPath}
					</span>
					<input
						className="flex-1 bg-transparent px-3 py-[7px] text-[13px] outline-none placeholder:text-text-3"
						value={body.route ?? ""}
						onChange={(e) => onPatch({ route: e.target.value })}
						placeholder={slugSuggest || "auto-generated"}
						disabled={disabled}
					/>
				</div>
			</Field>

			<Field
				label="Meta Description"
				error={fieldErrors.meta_description}
				wide
				column="meta_description"
				pending={pending}
			>
				<textarea
					rows={5}
					className={INPUT_TEXTAREA}
					value={description}
					onChange={(e) => onPatch({ meta_description: e.target.value })}
					placeholder="Concise summary shown in search engine result snippets. Aim for 150–160 characters."
					disabled={disabled}
				/>
				<span
					className={`mt-1 block text-[11px] ${description.length > 160 ? "text-warn" : "text-text-3"}`}
				>
					{description.length} / 160 characters
				</span>
			</Field>

			<Field
				label="Meta Keywords"
				hint="Most search engines ignore this."
				error={fieldErrors.meta_keywords}
				wide
				column="meta_keywords"
				pending={pending}
			>
				<input
					className={INPUT}
					value={body.meta_keywords ?? ""}
					onChange={(e) => onPatch({ meta_keywords: e.target.value })}
					disabled={disabled}
				/>
			</Field>

			<Check
				label="Hide from search engines"
				checked={Boolean(body.seo_invisible)}
				onChange={(v) => onPatch({ seo_invisible: v })}
				disabled={disabled}
				column="seo_invisible"
				pending={pending}
			/>
		</>
	);
};

interface SharingTabProps {
	body: PageEditBody;
	disabled?: boolean;
	onPatch: (patch: Partial<PageEditBody>) => void;
	pending?: PendingFieldInfo;
}

export const SharingTab = ({ body, disabled, onPatch, pending }: SharingTabProps) => {
	const og = body.open_graph ?? {};

	const setOg = (patch: Partial<NonNullable<PageEditBody["open_graph"]>>) => {
		onPatch({ open_graph: { ...og, ...patch } });
	};

	return (
		<>
			<Field
				label="Open Graph Title"
				hint="(defaults to the page title if left empty)"
				wide
				column="open_graph"
				pending={pending}
			>
				<input
					className={INPUT}
					value={og.title ?? ""}
					onChange={(e) => setOg({ title: e.target.value })}
					disabled={disabled}
				/>
			</Field>

			<Field
				label="Open Graph Description"
				hint="(defaults to the page's meta description if left empty)"
				wide
			>
				<input
					className={INPUT}
					value={og.description ?? ""}
					onChange={(e) => setOg({ description: e.target.value })}
					disabled={disabled}
				/>
			</Field>

			<div className="grid grid-cols-1 gap-[18px] md:grid-cols-2 md:gap-x-[22px]">
				<Field label="Open Graph Type">
					<select
						className={INPUT}
						value={og.type ?? ""}
						onChange={(e) => setOg({ type: e.target.value })}
						disabled={disabled}
					>
						<option value="">—</option>
						<option value="website">website</option>
						<option value="article">article</option>
						<option value="profile">profile</option>
						<option value="video.movie">video.movie</option>
					</select>
				</Field>
				<Field label="Open Graph Image" hint="(min 1200×630)">
					<input
						className={INPUT}
						value={og.image ?? ""}
						onChange={(e) => setOg({ image: e.target.value })}
						placeholder="https://"
						disabled={disabled}
					/>
				</Field>
			</div>
		</>
	);
};

// — Small visual primitives, kept local so the design ports cleanly without
// inflating the shared components dir. —

interface FieldProps {
	label: string;
	hint?: string;
	children: React.ReactNode;
	error?: string;
	wide?: boolean;
	/** Column this field maps to; enables pending markers when `pending` is set. */
	column?: string;
	pending?: PendingFieldInfo;
}

export const Field = ({ label, hint, children, error, wide, column, pending }: FieldProps) => {
	const showPending = Boolean(column && pending?.isPending(column));

	return (
		<div className={`flex flex-col gap-1.5 ${wide ? "w-full" : ""}`}>
			<span className="flex items-center gap-1.5 text-[11.5px] font-medium text-text-2">
				<span>
					{label}
					{hint && <span className="ml-1 text-[11px] text-text-3">{hint}</span>}
				</span>
				{showPending && <PendingBadge />}
			</span>
			{children}
			{showPending && column && pending && (
				<PendingFieldCompare
					published={pending.published(column)}
					pending={pending.current(column)}
					pendingLabel={pending.label}
				/>
			)}
			{error && (
				<span data-field-error className="text-[11.5px] text-danger">
					{error}
				</span>
			)}
		</div>
	);
};

interface CheckProps {
	label: string;
	checked: boolean;
	onChange: (next: boolean) => void;
	disabled?: boolean;
	/** Column this toggle maps to; shows a "Pending" badge when changed. */
	column?: string;
	pending?: PendingFieldInfo;
}

export const Check = ({ label, checked, onChange, disabled, column, pending }: CheckProps) => {
	const showPending = Boolean(column && pending?.isPending(column));

	return (
		<label className="inline-flex cursor-pointer items-center gap-2 text-[12.5px] text-text-2 has-[input:disabled]:cursor-not-allowed has-[input:disabled]:opacity-60">
			<input
				type="checkbox"
				className="h-4 w-4 rounded border-border accent-accent"
				checked={checked}
				onChange={(e) => onChange(e.target.checked)}
				disabled={disabled}
			/>
			{label}
			{showPending && <PendingBadge />}
		</label>
	);
};

interface DateInputProps {
	value: string;
	onChange: (next: string) => void;
	disabled?: boolean;
}

export const DateInput = ({ value, onChange, disabled }: DateInputProps) => {
	const normalized = value ? value.replace(" ", "T").slice(0, 16) : "";

	return (
		<div className="relative">
			<input
				type="datetime-local"
				className={`${INPUT} pr-8 font-mono text-[12.5px]`}
				value={normalized}
				onChange={(e) => onChange(e.target.value)}
				disabled={disabled}
			/>
			<Calendar
				size={14}
				className="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-text-3"
			/>
		</div>
	);
};

export const TemplateTag = ({ label }: { label: string }) => (
	<div className="inline-flex items-center gap-2 self-start rounded-md border border-border bg-surface-2 px-2.5 py-1 text-[11px] uppercase tracking-[0.06em] text-text-3">
		<span className="font-semibold text-text-3">Template:</span>
		<span className="font-medium text-text-2">{label}</span>
	</div>
);

export const NoResources = ({ reason }: { reason: "external" | "empty" | "redirect" }) => {
	const message =
		reason === "external"
			? "This page redirects to an external URL — its content lives elsewhere."
			: reason === "redirect"
				? "Redirect templates pass the URL through to a lower page; there is no content to edit on this page itself."
				: "This template has no fields configured.";

	return (
		<div className="grid place-items-center gap-2 rounded-md border border-dashed border-border bg-surface-2 px-4 py-12 text-center">
			<div className="text-[13px] font-medium text-text-2">
				There are no resources for the selected template.
			</div>
			<div className="max-w-md text-[12px] text-text-3">{message}</div>
		</div>
	);
};

interface WizardFooterProps {
	activeTab: TabValue;
	onSelect: (tab: TabValue) => void;
	primaryLabel: string;
	onPrimary: () => void;
	primaryDisabled?: boolean;
	/** When set (publishers only), renders a second accent "Save & Publish" button. */
	publishLabel?: string;
	onPublish?: () => void;
	publishDisabled?: boolean;
	secondary?: React.ReactNode;
	wizardMode?: boolean;
	onWizardCreate?: () => void;
	createLabel?: string;
}

export const WizardFooter = ({
	activeTab,
	onSelect,
	primaryLabel,
	onPrimary,
	primaryDisabled,
	publishLabel,
	onPublish,
	publishDisabled,
	secondary,
	wizardMode = false,
	onWizardCreate,
	createLabel,
}: WizardFooterProps) => {
	const showPublish = Boolean(publishLabel && onPublish);
	const index = PAGE_TABS.indexOf(activeTab);
	const isFirst = index === 0;
	const isLast = index === PAGE_TABS.length - 1;

	return (
		<div className="flex flex-wrap items-center gap-2 border-t border-border bg-surface-2 px-4 py-3">
			{!isFirst && (
				<button
					type="button"
					onClick={() => onSelect(PAGE_TABS[index - 1] ?? PAGE_TABS[0]!)}
					className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
				>
					<ChevronLeft size={13} />
					Back
				</button>
			)}

			{secondary}

			{/* Desktop-only spacer; on mobile the action group below claims its own row. */}
			<div className="hidden flex-1 sm:block" />

			{/* On mobile this is a full-width row (so Back sits alone above and the
			    save actions share a line); on desktop `contents` dissolves the wrapper
			    so the buttons lay out exactly as before. */}
			<div className="flex w-full flex-wrap items-center justify-end gap-2 sm:contents">
				{wizardMode && !isLast && (
					<button
						type="button"
						onClick={() =>
							onSelect(PAGE_TABS[index + 1] ?? PAGE_TABS[PAGE_TABS.length - 1]!)
						}
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
					>
						Next Step
						<ChevronLeft size={13} className="rotate-180" />
					</button>
				)}

				{wizardMode && onWizardCreate && (
					<button
						type="button"
						onClick={onWizardCreate}
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
						disabled={primaryDisabled}
					>
						{createLabel ?? "Create"}
					</button>
				)}

				<button
					type="button"
					onClick={onPrimary}
					className={
						showPublish
							? "inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] font-medium text-text-2 disabled:opacity-50 hover:bg-hover"
							: "inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-50 hover:bg-accent-hover"
					}
					disabled={primaryDisabled}
				>
					<Save size={13} />
					{primaryLabel}
				</button>

				{showPublish && (
					<button
						type="button"
						onClick={onPublish}
						className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-50 hover:bg-accent-hover"
						disabled={publishDisabled}
					>
						<Save size={13} />
						{publishLabel}
					</button>
				)}
			</div>
		</div>
	);
};

// — Shared Tailwind tokens (re-used from FieldRenderer's INPUT_CLASS but
// duplicated here to avoid a non-trivial import for two strings). —

export const INPUT =
	"w-full rounded-md border border-border-strong bg-surface px-2.5 py-[7px] text-[13px] text-text placeholder:text-text-3 focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-ring disabled:cursor-not-allowed disabled:opacity-60";

export const INPUT_TEXTAREA =
	"w-full min-h-[78px] rounded-md border border-border-strong bg-surface px-2.5 py-[7px] text-[13px] leading-6 text-text placeholder:text-text-3 focus:border-accent focus:outline-none focus:ring-[3px] focus:ring-accent-ring disabled:cursor-not-allowed disabled:opacity-60";

const computeParentPath = (page?: PageDetail): string => {
	if (!page) {
		return "/";
	}

	const path = page.path ?? "";
	const route = page.route ?? "";

	if (path && route && path.endsWith(route)) {
		const parent = path.slice(0, path.length - route.length);

		return parent.endsWith("/") ? parent : parent + "/";
	}

	return path ? "/" + path.replace(/\/?$/, "/").replace(/^\/+/, "") : "/";
};
