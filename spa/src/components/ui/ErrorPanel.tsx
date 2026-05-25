import { ApiError } from "@/types/api";

interface ErrorPanelProps {
	error: unknown;
}

export const ErrorPanel = ({ error }: ErrorPanelProps) => {
	const isApi = error instanceof ApiError;

	return (
		<div className="mt-6 rounded-md border border-danger/30 bg-danger-bg p-4 text-[13px]">
			<div className="mb-1 font-semibold text-danger">Failed to load pages</div>
			<div className="text-text-2">{(error as Error).message}</div>
			{isApi && (
				<dl className="mt-3 grid grid-cols-[max-content_1fr] gap-x-3 gap-y-1 font-mono text-[11.5px] text-text-3">
					<dt>status</dt>
					<dd>{error.status}</dd>
					<dt>code</dt>
					<dd>{error.code}</dd>
					{error.requestId && (
						<>
							<dt>request_id</dt>
							<dd>{error.requestId}</dd>
						</>
					)}
				</dl>
			)}
		</div>
	);
};
