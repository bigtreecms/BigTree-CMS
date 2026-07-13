import type { AuthUser } from "@/auth/store";
import { isDeveloper } from "@/lib/permissions";

/**
 * Where to send the user after a successful login (or when already-authed on /login).
 * Developers with pending core DB migrations always go to the forced migration gate.
 */
export const postLoginPath = (
	user: AuthUser | null | undefined,
	fallback = "/dashboard"
): string => {
	if (isDeveloper(user) && user?.migrations_pending) {
		return "/developer/migrations";
	}

	return fallback || "/dashboard";
};
