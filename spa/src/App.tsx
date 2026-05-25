import { QueryClientProvider } from "@tanstack/react-query";
import { RouterProvider } from "react-router-dom";
import { api } from "@/api/client";
import { queryClient } from "@/lib/queryClient";
import { router } from "@/routes";
import { useAuthStore } from "@/auth/store";
import { useEffect } from "react";

/**
 * Top-level component. On mount we kick off the auth boot probe (try the
 * refresh cookie); the result hydrates the auth store and route guards
 * unblock. Order of providers matters: QueryClient must be outside so route
 * components can use React Query.
 */
export function App() {
	const hydrating = useAuthStore((s) => s.hydrating);

	useEffect(() => {
		void api.bootstrapSession();
	}, []);

	return (
		<QueryClientProvider client={queryClient}>
			{/* The router itself handles the "hydrating" state via ProtectedRoute;
			    we just need to ensure App doesn't unmount the router. */}
			<RouterProvider router={router} />
			{/* When hydrating is true, ProtectedRoute renders its spinner; once
			    the boot probe resolves we either land on the requested page
			    (still authed) or get redirected to /login. */}
			{hydrating && null}
		</QueryClientProvider>
	);
}
