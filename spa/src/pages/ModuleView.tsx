import { Navigate, useParams } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";

import { modulesApi } from "@/api/endpoints/modules";
import { ViewRenderer } from "@/renderer/views/ViewRenderer";

/**
 * /modules/:id/view/:sid — the per-view runtime page.
 *
 * We fetch the module record (for the breadcrumb title) and the per-module
 * view config list, then pick the requested view out of it and hand the data
 * off to <ViewRenderer />. The actual rows come from inside the renderer via
 * /modules/{id}/entries so pagination/search/sort state stays local to the
 * view.
 */
export const ModuleView = () => {
	const { id, sid } = useParams<{ id: string; sid: string }>();
	const moduleId = id ? Number.parseInt(id, 10) : NaN;
	const viewId = sid ? Number.parseInt(sid, 10) : NaN;

	const moduleQuery = useQuery({
		queryKey: ["modules", "detail", moduleId],
		queryFn: () => modulesApi.get(moduleId),
		enabled: Number.isFinite(moduleId),
	});

	const viewsQuery = useQuery({
		queryKey: ["modules", "views", moduleId],
		queryFn: () => modulesApi.views(moduleId),
		enabled: Number.isFinite(moduleId),
	});

	if (!Number.isFinite(moduleId) || !Number.isFinite(viewId)) {
		return <Navigate to="/modules" replace />;
	}

	const view = viewsQuery.data?.find((v) => Number(v.id) === viewId);

	const breadcrumbs = [
		{ label: "Modules", to: "/modules" },
		{ label: moduleQuery.data?.name ?? "…", to: `/modules/${moduleId}` },
		{ label: view?.title ?? "View" },
	];

	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-4">
			<Breadcrumb items={breadcrumbs} />

			<PageHead
				title={view?.title ?? moduleQuery.data?.name ?? "View"}
				sub={view?.description ? view.description : undefined}
			/>

			{viewsQuery.isLoading ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					Loading view…
				</div>
			) : !view ? (
				<div className="rounded-xl border border-border bg-surface p-9 text-center text-[13px] text-text-3">
					That view doesn't exist on this module.
				</div>
			) : (
				<ViewRenderer moduleId={moduleId} view={view} />
			)}
		</div>
	);
};
