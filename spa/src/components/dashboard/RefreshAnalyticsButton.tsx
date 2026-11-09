import { RefreshCw } from "lucide-react";

import { Button } from "@/components/ui/Button";
import { dashboardApi } from "@/api/endpoints/dashboard";
import { useToastMutation } from "@/hooks/useToastMutation";
import { queryKeys } from "@/lib/queryKeys";

/**
 * Rebuild the Google Analytics cache now instead of waiting for the scheduled
 * refresh, like the legacy analytics screen's "Refresh". The rebuild makes
 * several GA4 report calls, so it can take a while on a large site.
 */
export const RefreshAnalyticsButton = () => {
	const refreshMutation = useToastMutation({
		mutationFn: () => dashboardApi.refreshAnalytics(),
		invalidate: [queryKeys.dashboard.analytics(), ["dashboard"]],
		successMessage: "Analytics refreshed",
		errorMessage: "Could not refresh analytics",
	});

	return (
		<Button
			icon={<RefreshCw size={13} />}
			loading={refreshMutation.isPending}
			loadingLabel="Refreshing…"
			onClick={() => refreshMutation.mutate()}
		>
			Refresh
		</Button>
	);
};
