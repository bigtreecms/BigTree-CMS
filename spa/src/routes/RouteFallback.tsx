import { Loader2 } from "lucide-react";

/**
 * Suspense fallback shown while a lazily-loaded route chunk is fetched.
 *
 * Mirrors the lightweight centered loader used by ProtectedRoute so the
 * transition between eager and code-split pages feels consistent.
 */
export const RouteFallback = () => {
	return (
		<div className="grid min-h-[40vh] place-items-center text-text-3 text-[12.5px]">
			<span className="inline-flex items-center gap-2">
				<Loader2 size={15} className="animate-spin text-accent" />
				Loading…
			</span>
		</div>
	);
};
