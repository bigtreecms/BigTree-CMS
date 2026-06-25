import { Loading } from "@/components/ui/Loading";

/**
 * Suspense fallback shown while a lazily-loaded route chunk is fetched.
 *
 * Mirrors the lightweight centered loader used by ProtectedRoute so the
 * transition between eager and code-split pages feels consistent.
 */
export const RouteFallback = () => {
	return (
		<div className="grid min-h-[40vh] place-items-center">
			<Loading />
		</div>
	);
};
