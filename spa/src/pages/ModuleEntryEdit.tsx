import { Navigate, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";

import { autoModulesApi } from "@/api/endpoints/auto-modules";
import { modulesApi, type ModuleForm, type ModuleView } from "@/api/endpoints/modules";
import { FormRenderer } from "@/renderer/forms/FormRenderer";
import { useLock } from "@/hooks/useLock";
import { toast } from "@/lib/toast";

/**
 * /modules/:id/view/:sid/edit/:eid
 *
 * Loads the entry's current data, acquires a `module:{id}` lock for the
 * lifetime of the page, and renders the same FormRenderer used by Add. The
 * lock owner banner appears at the top of the form when another user has
 * the row open; in that case the form goes read-only.
 *
 * On submit, PATCH /modules/:id/entries/:eid — the auto-module service
 * decides whether to apply directly or to create a pending change row based
 * on the user's per-module permission level.
 */
export const ModuleEntryEdit = () => {
	const { id, sid, eid } = useParams<{ id: string; sid: string; eid: string }>();
	const moduleId = id ?? "";
	const viewId = sid ?? "";
	const entryId = eid ? Number.parseInt(eid, 10) : NaN;
	const navigate = useNavigate();
	const queryClient = useQueryClient();

	const moduleQuery = useQuery({
		queryKey: ["modules", "detail", moduleId],
		queryFn: () => modulesApi.get(moduleId),
		enabled: moduleId !== "",
	});

	const viewsQuery = useQuery({
		queryKey: ["modules", "views", moduleId],
		queryFn: () => modulesApi.views(moduleId),
		enabled: moduleId !== "",
	});

	const formsQuery = useQuery({
		queryKey: ["modules", "forms", moduleId],
		queryFn: () => modulesApi.forms(moduleId),
		enabled: moduleId !== "",
	});

	const entryQuery = useQuery({
		queryKey: ["module-entries", moduleId, "detail", entryId, viewId],
		queryFn: () => autoModulesApi.get(moduleId, entryId, viewId),
		enabled: moduleId !== "" && Number.isFinite(entryId),
	});

	const lock = useLock({
		table: `module:${moduleId}`,
		itemId: entryId,
		enabled: moduleId !== "" && Number.isFinite(entryId),
	});

	const updateMutation = useMutation({
		mutationFn: (values: Record<string, unknown>) =>
			autoModulesApi.update(moduleId, entryId, values, viewId),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["module-entries", moduleId] });
			toast.success("Entry saved");
			navigate(`/modules/${encodeURIComponent(moduleId)}/view/${encodeURIComponent(viewId)}`);
		},
	});

	if (moduleId === "" || viewId === "" || !Number.isFinite(entryId)) {
		return <Navigate to="/modules" replace />;
	}

	const view = viewsQuery.data?.find((v) => v.id === viewId);
	const form = resolveForm(view, formsQuery.data ?? []);
	const initialValues = pickItemValues(entryQuery.data);

	const isLoading =
		viewsQuery.isLoading ||
		formsQuery.isLoading ||
		moduleQuery.isLoading ||
		entryQuery.isLoading;

	const breadcrumbs = [
		{ label: "Modules", to: "/modules" },
		{ label: moduleQuery.data?.name ?? "…", to: `/modules/${encodeURIComponent(moduleId)}` },
		...(view
			? [
					{
						label: view.title,
						to: `/modules/${encodeURIComponent(moduleId)}/view/${encodeURIComponent(viewId)}`,
					},
				]
			: []),
		{ label: "Edit" },
	];

	const readOnly = lock.ownedByOther;

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={breadcrumbs} />

			<PageHead title={form ? `Edit ${form.title}` : "Edit entry"} />

			{readOnly && (
				<div className="mb-3 rounded-md border border-border bg-surface-2 px-3 py-2 text-[12.5px] text-text-2">
					Locked by {lock.lockOwner?.name ?? "another user"} — editing is disabled.
				</div>
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
					moduleId={moduleId}
					entryId={entryId}
					disabled={readOnly}
					onCancel={() =>
						navigate(
							`/modules/${encodeURIComponent(moduleId)}/view/${encodeURIComponent(viewId)}`
						)
					}
					submitLabel="Save"
					onSubmit={async (values) => {
						await updateMutation.mutateAsync(values);
					}}
				/>
			)}
		</div>
	);
};

const resolveForm = (
	view: ModuleView | undefined,
	forms: ModuleForm[]
): ModuleForm | undefined => {
	if (forms.length === 0) {
		return undefined;
	}

	if (view?.related_form) {
		const byId = forms.find((f) => f.id === view.related_form);

		if (byId) {
			return byId;
		}
	}

	if (view?.table) {
		const byTable = forms.find((f) => f.table === view.table);

		if (byTable) {
			return byTable;
		}
	}

	return forms[0];
};

/**
 * Strip out the BigTree envelope (`item`, pending-change metadata) and
 * surface just the row's column values for the form to consume.
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
