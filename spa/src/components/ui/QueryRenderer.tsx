import { Loading } from "@/components/ui/Loading";
import { describeApiError } from "@/lib/errorHandling";

interface QueryRendererProps {
	isLoading?: boolean;
	error?: unknown;
	isEmpty?: boolean;
	empty?: React.ReactNode;
	loading?: React.ReactNode;
	children: React.ReactNode;
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
