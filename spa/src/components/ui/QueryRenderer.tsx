import { ApiError } from "@/types/api";
import { Loading } from "@/components/ui/Loading";

interface QueryRendererProps {
	isLoading?: boolean;
	error?: unknown;
	isEmpty?: boolean;
	empty?: React.ReactNode;
	children: React.ReactNode;
}

export const QueryRenderer = ({
	isLoading,
	error,
	isEmpty,
	empty,
	children,
}: QueryRendererProps) => {
	if (isLoading) {
		return <Loading />;
	}

	if (error) {
		const message = error instanceof ApiError ? error.message : "Failed to load.";

		return <div className="text-[12.5px] text-danger">{message}</div>;
	}

	if (isEmpty && empty != null) {
		return <>{empty}</>;
	}

	return <>{children}</>;
};
