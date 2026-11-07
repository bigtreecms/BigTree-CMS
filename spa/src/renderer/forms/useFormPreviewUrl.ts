import { useQuery } from "@tanstack/react-query";

import { modulesApi, type ModuleForm, type ModuleView } from "@/api/endpoints/modules";
import { queryKeys } from "@/lib/queryKeys";

/**
 * The view a form's entries are previewed through, matched the way the legacy
 * BigTreeAutoModule::getRelatedViewForForm did: a view that names this form as
 * its related form wins, otherwise the first view on the same table.
 */
export const relatedViewForForm = (
	views: ModuleView[],
	form: Pick<ModuleForm, "id" | "table">
): ModuleView | undefined =>
	views.find((view) => Boolean(view.related_form) && view.related_form === form.id) ??
	views.find((view) => Boolean(form.table) && view.table === form.table);

/**
 * The related view's `preview_url` for a module form, or undefined when there
 * isn't one — in which case the form offers no "Save & Preview".
 */
export const useFormPreviewUrl = (
	moduleId: string,
	form: Pick<ModuleForm, "id" | "table"> | undefined
): string | undefined => {
	const viewsQuery = useQuery({
		queryKey: queryKeys.modules.views(moduleId),
		queryFn: () => modulesApi.views(moduleId),
		enabled: moduleId !== "" && Boolean(form),
	});

	if (!form || !viewsQuery.data) {
		return undefined;
	}

	const url = relatedViewForForm(viewsQuery.data, form)?.preview_url?.trim();

	return url ? url : undefined;
};
