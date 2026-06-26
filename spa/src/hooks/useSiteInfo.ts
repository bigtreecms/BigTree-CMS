import { useQuery } from "@tanstack/react-query";

import { siteApi, type SiteInfo } from "@/api/endpoints/system";
import { queryKeys } from "@/lib/queryKeys";

/**
 * Site identity + link roots (`GET /system/site`), cached for the session.
 * Shares the ["system", "site"] key with the TopBar's query so the payload is
 * fetched once no matter how many consumers mount.
 */
export const useSiteInfo = (): SiteInfo | undefined => {
	const query = useQuery({
		queryKey: queryKeys.system.site(),
		queryFn: () => siteApi.get(),
		staleTime: Infinity,
	});

	return query.data;
};
