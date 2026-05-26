import { Navigate, useNavigate, useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";

import { autoModulesApi } from "@/api/endpoints/auto-modules";
import { modulesApi, type ModuleForm, type ModuleView } from "@/api/endpoints/modules";
import { FormRenderer } from "@/renderer/forms/FormRenderer";
import { toast } from "@/lib/toast";

/**
 * /modules/:id/view/:sid/add
 *
 * Picks the form to render via this fallback chain:
 *   1) view.related_form  (the explicit link)
 *   2) the module form whose `table` matches the view's `table`
 *   3) the module's first form
 * If none of those resolve, we render an explanatory empty state.
 *
 * On submit, POST /modules/:id/entries. The auto-module service inserts the
 * row (or creates a pending change row, depending on the user's level), then
 * we invalidate the entries cache and bounce back to the view list.
 */
export const ModuleEntryAdd = () => {
	const { id, sid } = useParams<{ id: string; sid: string }>();
	const moduleId = id ?? "";
	const viewId = sid ?? "";
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

	const createMutation = useMutation({
		mutationFn: (values: Record<string, unknown>) =>
			autoModulesApi.create(moduleId, values),
		onSuccess: () => {
			queryClient.invalidateQueries({ queryKey: ["module-entries", moduleId] });
			toast.success("Entry created");
			navigate(`/modules/${encodeURIComponent(moduleId)}/view/${encodeURIComponent(viewId)}`);
		},
	});

	if (moduleId === "" || viewId === "") {
		return <Navigate to="/modules" replace />;
	}

	const view = viewsQuery.data?.find((v) => v.id === viewId);
	const form = resolveForm(view, formsQuery.data ?? []);

	const isLoading = viewsQuery.isLoading || formsQuery.isLoading || moduleQuery.isLoading;

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
		{ label: "Add" },
	];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={breadcrumbs} />

			<PageHead title={form ? `Add ${form.title}` : `Add ${view?.title ?? "entry"}`} />

			{isLoading ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading form…
				</div>
			) : !form ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					This module doesn't have a form configured.
				</div>
			) : (
				<FormRenderer
					form={form}
					onCancel={() =>
						navigate(
							`/modules/${encodeURIComponent(moduleId)}/view/${encodeURIComponent(viewId)}`
						)
					}
					submitLabel="Create"
					onSubmit={async (values) => {
						await createMutation.mutateAsync(values);
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
