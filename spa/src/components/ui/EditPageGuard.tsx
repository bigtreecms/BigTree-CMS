import type { ReactNode } from "react";

import { PageContainer } from "@/components/shell/PageContainer";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Loading } from "@/components/ui/Loading";

interface EditPageGuardProps {
	/** Content width tier, passed straight through to `PageContainer`. */
	width?: "wide" | "medium" | "narrow" | "xwide";
	/** True while the record is loading (edit mode). Gate on `!isAdd`. */
	loading?: boolean;
	/** The detail query's error, if any. Pass a falsy value in add mode. */
	error?: unknown;
	children: ReactNode;
}

/**
 * The loading / error / content tri-state every developer add-edit page wrapped
 * around its form: a full-page `Loading variant="card"` while the record loads,
 * an `ErrorPanel` if the fetch failed, otherwise the form — all inside the same
 * `PageContainer` width. Callers gate `loading`/`error` on `!isAdd` so add mode
 * renders the children straight through.
 */
export const EditPageGuard = ({ width, loading, error, children }: EditPageGuardProps) => {
	if (loading) {
		return (
			<PageContainer width={width}>
				<Loading variant="card" />
			</PageContainer>
		);
	}

	if (error) {
		return (
			<PageContainer width={width}>
				<ErrorPanel error={error} />
			</PageContainer>
		);
	}

	return <>{children}</>;
};
