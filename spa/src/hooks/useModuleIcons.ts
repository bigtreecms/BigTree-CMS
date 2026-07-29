import { useQuery } from "@tanstack/react-query";

import { modulesApi } from "@/api/endpoints/modules";
import { queryKeys } from "@/lib/queryKeys";

/**
 * The module-icon vocabulary (GET /module-icons), shared by the icon pickers
 * (IconPicker, IconSelect). The list is served from the one PHP source
 * (BigTree\Api\ModuleIcons) so the SPA no longer hardcodes its own copy of the
 * slugs. It is install-static — it only changes when core ships a new build —
 * so it is cached effectively forever for the session; `iconFor` maps each slug
 * onto a Lucide glyph for rendering.
 *
 * Returns the plain `string[]` (empty while loading), so a picker can render the
 * grid without unpacking the `{ icons }` envelope.
 */
export const useModuleIcons = (): string[] => {
	const query = useQuery({
		queryKey: queryKeys.modules.icons(),
		queryFn: () => modulesApi.icons(),
		select: (response) => response.icons,
		staleTime: Infinity,
	});

	return query.data ?? [];
};
