import type { ReactNode } from "react";
import { ShieldAlert } from "lucide-react";

import { useAuthStore } from "@/auth/store";
import { hasLevel } from "@/lib/permissions";
import { Card } from "@/components/ui/Card";

interface AccessDeniedProps {
	message?: string;
	title?: string;
}

/**
 * Standard "you don't have access to this screen" panel. Routes should
 * render this rather than navigating away so the user has clear feedback
 * about *why* they hit a wall — and so they can use back-nav normally.
 */
export const AccessDenied = ({
	title = "Access denied",
	message = "You don't have permission to view this section.",
}: AccessDeniedProps) => {
	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-12" data-testid="access-denied">
			<Card className="mx-auto flex max-w-md flex-col items-center p-8 text-center">
				<div className="mb-3 flex size-12 items-center justify-center rounded-full bg-danger/10 text-danger">
					<ShieldAlert size={22} />
				</div>

				<h1 className="text-[15px] font-semibold tracking-[-0.01em] text-text">{title}</h1>
				<p className="mt-1 text-[13px] text-text-2">{message}</p>
			</Card>
		</div>
	);
};

interface RequireLevelProps {
	children: ReactNode;
	fallback?: ReactNode;
	level: number;
}

/**
 * Route-level permission guard. Wrap a route element to require a minimum
 * `user.level`. If the check fails we render <AccessDenied /> (or a custom
 * fallback) in place of the protected subtree — we deliberately do not
 * redirect, so the user has a clear breadcrumb of why they can't proceed.
 */
export const RequireLevel = ({ level, fallback, children }: RequireLevelProps) => {
	const user = useAuthStore((s) => s.user);

	if (!hasLevel(user, level)) {
		return <>{fallback ?? <AccessDenied />}</>;
	}

	return <>{children}</>;
};
