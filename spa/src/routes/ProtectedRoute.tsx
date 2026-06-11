import { useEffect } from "react";
import { Navigate, Outlet, useLocation } from "react-router-dom";

import { authApi } from "@/auth/endpoints";
import { useAuthStore } from "@/auth/store";

/**
 * Route guard. While the auth boot probe is in flight we render a minimal
 * loading state — once the store is hydrated, we either render the protected
 * subtree or redirect to /login with the original path stashed in state so
 * the post-login flow can return the user where they were.
 */
export const ProtectedRoute = () => {
	const hydrating = useAuthStore((s) => s.hydrating);
	const authenticated = useAuthStore((s) => !!s.accessToken && !!s.user);
	const location = useLocation();

	// Bridge the token auth into the legacy PHP session (front-end BigTree bar
	// / on-page editing). Idempotent per page load, fire-and-forget — covers
	// every login path and the persisted-token boot in one place.
	useEffect(() => {
		if (authenticated) {
			authApi.establishPhpSession();
		}
	}, [authenticated]);

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

	return <Outlet />;
};
