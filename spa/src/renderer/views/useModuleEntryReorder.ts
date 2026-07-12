import { useQueryClient } from "@tanstack/react-query";

import { autoModulesApi } from "@/api/endpoints/auto-modules";
import { useToastMutation } from "@/hooks/useToastMutation";
import { queryKeys } from "@/lib/queryKeys";

/**
 * Shared reorder mutation for draggable + nested module entry views.
 * On failure, invalidates the view query so the UI restores server order.
 */
export const useModuleEntryReorder = (moduleId: string, viewId: string) => {
	const queryClient = useQueryClient();

	return useToastMutation({
		mutationFn: (ids: Array<string | number>) => autoModulesApi.reorder(moduleId, ids, viewId),
		errorMessage: "Couldn't save the new order",
		onError: () =>
			queryClient.invalidateQueries({
				queryKey: queryKeys.moduleEntries.view(moduleId, viewId),
			}),
	});
};
