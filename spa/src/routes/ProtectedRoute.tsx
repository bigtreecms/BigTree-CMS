import { Navigate, Outlet, useLocation } from "react-router-dom";

import { useAuthStore } from "@/auth/store";

/**
 * Route guard. While the auth boot probe is in flight we render a minimal
 * loading state — once the store is hydrated, we either render the protected
 * subtree or redirect to /login with the original path stashed in state so
 * the post-login flow can return the user where they were.
 */
export function ProtectedRoute() {
	const hydrating = useAuthStore((s) => s.hydrating);
	const authenticated = useAuthStore((s) => !!s.accessToken && !!s.user);
	const location = useLocation();

	if (hydrating) {
		return (
			<div className="grid min-h-screen place-items-center text-text-3 text-[12.5px]">
				<span>Loading…</span>
			</div>
		);
	}

	if (!authenticated) {
		return (
			<Navigate to="/login" replace state={{ from: location.pathname }} />
		);
	}

	return <Outlet />;
}
