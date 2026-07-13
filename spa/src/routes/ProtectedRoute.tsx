import { useEffect } from "react";
import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";

import { authApi } from "@/auth/endpoints";
import { useAuthStore } from "@/auth/store";
import { systemApi } from "@/api/endpoints/system";
import { isDeveloper } from "@/lib/permissions";
import { queryKeys } from "@/lib/queryKeys";

/**
 * Route guard. While the auth boot probe is in flight we render a minimal
 * loading state — once the store is hydrated, we either render the protected
 * subtree or redirect to /login with the original path stashed in state so
 * the post-login flow can return the user where they were.
 *
 * Developers with pending core DB migrations are forced to
 * `/developer/migrations` until the queue is empty. The gate is driven by a
 * live GET /system/upgrade/migrations check (not only the user flag on the
 * JWT payload), so it stays consistent across login, refresh, and deploys.
 */
export const ProtectedRoute = () => {
	const hydrating = useAuthStore((s) => s.hydrating);
	const authenticated = useAuthStore((s) => !!s.accessToken && !!s.user);
	const user = useAuthStore((s) => s.user);
	const setUser = useAuthStore((s) => s.setUser);
	const location = useLocation();

	const developer = isDeveloper(user);
	const onMigrationsPage = location.pathname.startsWith("/developer/migrations");

	// Live migration queue — authoritative for the gate.
	const migrationsQ = useQuery({
		queryKey: queryKeys.system.migrations(),
		queryFn: () => systemApi.upgrade.migrations(),
		enabled: authenticated && developer && !hydrating,
		staleTime: 15_000,
		retry: 1,
		refetchOnWindowFocus: true,
	});

	// Bridge the token auth into the legacy PHP session (front-end BigTree bar
	// / on-page editing). Idempotent per page load, fire-and-forget — covers
	// every login path and the persisted-token boot in one place.
	useEffect(() => {
		if (authenticated) {
			authApi.establishPhpSession();
		}
	}, [authenticated]);

	// Keep user.migrations_pending in sync with the live queue so Login /
	// postLoginPath and any other flag readers stay consistent.
	useEffect(() => {
		if (!user || !migrationsQ.data || !developer) {
			return;
		}

		const pending = (migrationsQ.data.queue?.length ?? 0) > 0;

		if (!!user.migrations_pending !== pending) {
			setUser({ ...user, migrations_pending: pending });
		}
	}, [user, migrationsQ.data, developer, setUser]);

	if (hydrating) {
		return (
			<div className="grid min-h-screen place-items-center text-text-3 text-[12.5px]">
				<span>Loading…</span>
			</div>
		);
	}

	if (!authenticated) {
		return <Navigate to="/login" replace state={{ from: location.pathname }} />;
	}

	// Wait for the live queue before letting developers into the rest of admin.
	if (developer && migrationsQ.isLoading && !migrationsQ.data) {
		return (
			<div className="grid min-h-screen place-items-center text-text-3 text-[12.5px]">
				<span>Checking database…</span>
			</div>
		);
	}

	const livePending =
		migrationsQ.data != null
			? (migrationsQ.data.queue?.length ?? 0) > 0
			: !!user?.migrations_pending;

	if (developer && livePending && !onMigrationsPage) {
		return <Navigate to="/developer/migrations" replace />;
	}

	// If we're on the migrations page but nothing is pending, leave.
	if (developer && onMigrationsPage && migrationsQ.data && !livePending) {
		return <Navigate to="/dashboard" replace />;
	}

	return <Outlet />;
};
