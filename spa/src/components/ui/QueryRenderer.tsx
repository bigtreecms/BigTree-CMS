import { Loading } from "@/components/ui/Loading";
import { describeApiError } from "@/lib/errorHandling";

interface QueryRendererProps {
	children: React.ReactNode;
	empty?: React.ReactNode;
	error?: unknown;
	isEmpty?: boolean;
	isLoading?: boolean;
	loading?: React.ReactNode;
}

export const QueryRenderer = ({
	isLoading,
	error,
	isEmpty,
	empty,
	loading,
	children,
}: QueryRendererProps) => {
	if (isLoading) {
		return loading != null ? <>{loading}</> : <Loading />;
	}

	if (error) {
		return (
			<div className="text-[12.5px] text-danger">
				{describeApiError(error, "Failed to load.")}
			</div>
		);
	}

	if (isEmpty && empty != null) {
		return <>{empty}</>;
	}

	return <>{children}</>;
};
