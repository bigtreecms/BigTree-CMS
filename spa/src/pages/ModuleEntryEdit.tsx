import { useNavigate } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { PageHead } from "@/components/shell/PageHead";
import { LockBanner } from "@/components/ui/LockBanner";

import { autoModulesApi } from "@/api/endpoints/auto-modules";
import { modulesApi } from "@/api/endpoints/modules";
import type { Tag } from "@/api/endpoints/tags";
import { FormRenderer } from "@/renderer/forms/FormRenderer";
import type { OpenGraphValue } from "@/renderer/forms/OpenGraphSection";
import { useLock } from "@/hooks/useLock";
import { modulePath, moduleActionPath } from "@/lib/moduleActions";
import { draftOwnerLabel } from "@/lib/fieldComparison";
import { useAuthStore } from "@/auth/store";
import { useModuleContext } from "@/pages/ModuleLayout";
import { isPersistedEntryId, numericEntryId } from "@/renderer/views/viewHelpers";
import { toast } from "@/lib/toast";

interface ModuleEntryEditProps {
	formId: string;
	// May be a real numeric id or a "p"-prefixed pending id (e.g. "p5"); also a
	// raw string straight from the URL. Validated below.
	entryId: number | string;
}

/**
 * Edit screen, rendered by <ModuleDispatcher /> when the active action relates to
 * a form and carries an entry id command (e.g. `/modules/news/edit/5`). The form
 * id comes from the resolved action and is used both to render the form and as
 * the table ref (`?form=`) for the entry fetch / save.
 *
 * Acquires a `module:{id}` lock for the lifetime of the page; the form goes
 * read-only while another user holds the row. On submit, PATCH
 * /modules/:id/entries/:eid — the service applies directly or creates a pending
 * change row based on the user's per-module permission level.
 */
export const ModuleEntryEdit = ({ formId, entryId }: ModuleEntryEditProps) => {
	const { moduleId, module, actions } = useModuleContext();
	const navigate = useNavigate();
	const queryClient = useQueryClient();
	const currentUserId = useAuthStore((s) => s.user?.id);

	// A pending ("p"-prefixed) entry has no live row id; `liveId` is null for it,
	// so MTM relation lookups (which query the live connecting table) are skipped.
	const validEntry = isPersistedEntryId(entryId);
	const liveId = numericEntryId(entryId);

	const formsQuery = useQuery({
		queryKey: ["modules", "forms", moduleId],
		queryFn: () => modulesApi.forms(moduleId),
		enabled: moduleId !== "",
	});

	const entryQuery = useQuery({
		queryKey: ["module-entries", moduleId, "detail", String(entryId), formId],
		queryFn: () => autoModulesApi.get(moduleId, entryId, { form: formId }),
		enabled: moduleId !== "" && validEntry,
	});

	const lock = useLock({
		table: `module:${moduleId}`,
		itemId: entryId,
		enabled: moduleId !== "" && validEntry,
	});

	const moduleQuery = useQuery({
		queryKey: ["modules", "detail", moduleId],
		queryFn: () => modulesApi.get(moduleId),
		enabled: moduleId !== "",
	});

	const form = formsQuery.data?.find((f) => f.id === formId);

	const returnAction = form?.return_view
		? actions.find((a) => a.view === form.return_view)
		: undefined;
	const returnPath =
		module && returnAction
			? moduleActionPath(module, returnAction)
			: module
				? modulePath(module)
				: "/modules";

	const updateMutation = useMutation({
		mutationFn: ({ values, publish }: { values: Record<string, unknown>; publish: boolean }) =>
			autoModulesApi.update(moduleId, entryId, values, { form: formId }, publish),
		onSuccess: (result) => {
			queryClient.invalidateQueries({ queryKey: ["module-entries", moduleId] });

			if (result && typeof result === "object" && "pending" in result) {
				toast.success("Draft saved", {
					description: "Your change is pending publisher approval.",
				});
			} else {
				toast.success("Entry published");
			}

			navigate(returnPath);
		},
	});

	const initialValues = pickItemValues(entryQuery.data);
	const isLoading = formsQuery.isLoading || entryQuery.isLoading;
	const readOnly = lock.ownedByOther;

	// Surface which fields carry queued changes so the form can badge them and
	// offer a published-vs-draft comparison. Only "updated"/"pending" entries
	// have anything to show.
	const entryStatus = entryQuery.data?.status;
	const pendingStatus =
		entryStatus === "updated" || entryStatus === "pending" ? entryStatus : undefined;
	const pendingFields = pendingStatus ? entryQuery.data?.changed_fields : undefined;
	const publishedValues = pendingStatus ? (entryQuery.data?.original ?? null) : undefined;
	const pendingLabel = pendingStatus
		? draftOwnerLabel(entryQuery.data?.owner, entryQuery.data?.owner_name, currentUserId)
		: undefined;

	return (
		<>
			<PageHead title={form ? `Edit ${form.title}` : "Edit entry"} />

			{readOnly && (
				<LockBanner
					owner={lock.lockOwner}
					lockedAt={lock.lockedAt}
					onUnlock={lock.forceUnlock}
				/>
			)}

			{isLoading ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading entry…
				</div>
			) : !form ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					This module doesn't have a form configured.
				</div>
			) : (
				<FormRenderer
					form={form}
					initialValues={initialValues}
					initialTags={pickTags(entryQuery.data)}
					initialOpenGraph={pickOpenGraph(entryQuery.data)}
					moduleId={moduleId}
					entryId={liveId}
					pendingFields={pendingFields}
					publishedValues={publishedValues}
					pendingStatus={pendingStatus}
					pendingLabel={pendingLabel}
					disabled={readOnly}
					onCancel={() => navigate(returnPath)}
					submitLabel="Save"
					canPublish={moduleQuery.data?.access === "p"}
					onSubmit={async (values, opts) => {
						await updateMutation.mutateAsync({
							values,
							publish: Boolean(opts?.publish),
						});
					}}
				/>
			)}
		</>
	);
};

/**
 * Strip out the BigTree envelope (`item`, pending-change metadata) and surface
 * just the row's column values for the form to consume.
 */
const pickItemValues = (
	entry: { item?: Record<string, unknown> } | undefined
): Record<string, unknown> | undefined => {
	if (!entry) {
		return undefined;
	}

	const item = entry.item;

	if (item && typeof item === "object") {
		return item;
	}

	// In some shapes the row is the response itself.
	return entry as unknown as Record<string, unknown>;
};

/** The entry's current tags from the envelope (full rows, used as chips). */
const pickTags = (entry: Record<string, unknown> | undefined): Tag[] => {
	const tags = entry?.tags;

	if (!Array.isArray(tags)) {
		return [];
	}

	return tags.filter(
		(t): t is Tag => Boolean(t) && typeof t === "object" && "id" in (t as object)
	);
};

/** The entry's Open Graph row from the envelope (null when unset). */
const pickOpenGraph = (entry: Record<string, unknown> | undefined): OpenGraphValue | null => {
	const og = entry?.open_graph;

	if (og && typeof og === "object" && !Array.isArray(og)) {
		return og as OpenGraphValue;
	}

	return null;
};
