import { useQuery } from "@tanstack/react-query";

import { PageHead } from "@/components/shell/PageHead";
import { EmptyState } from "@/components/ui/EmptyState";
import { Loading } from "@/components/ui/Loading";

import { modulesApi } from "@/api/endpoints/modules";
import { useModuleContext } from "@/pages/ModuleLayout";
import { queryKeys } from "@/lib/queryKeys";
import { ViewRenderer } from "@/renderer/views/ViewRenderer";

interface ModuleViewProps {
	viewId: string;
}

/**
 * The per-view runtime page, rendered by <ModuleDispatcher /> when the active
 * action relates to a view. The module is taken from context (resolved by route
 * in <ModuleLayout />); the view id comes from the resolved action.
 *
 * We fetch the per-module view config list, pick the requested view out of it,
 * and hand it off to <ViewRenderer />. The actual rows come from inside the
 * renderer via /modules/{id}/entries so pagination/search/sort state stays local.
 */
export const ModuleView = ({ viewId }: ModuleViewProps) => {
	const { moduleId, module } = useModuleContext();

	const viewsQuery = useQuery({
		queryKey: queryKeys.modules.views(moduleId),
		queryFn: () => modulesApi.views(moduleId),
		enabled: moduleId !== "",
	});

	const view = viewsQuery.data?.find((v) => v.id === viewId);

	return (
		<>
			<PageHead
				sub={view?.description ? view.description : undefined}
				title={view?.title ?? module?.name ?? "View"}
			/>

			{viewsQuery.isLoading ? (
				<Loading label="Loading view…" variant="card" />
			) : !view ? (
				<EmptyState>That view doesn't exist on this module.</EmptyState>
			) : (
				<ViewRenderer moduleId={moduleId} view={view} />
			)}
		</>
	);
};
