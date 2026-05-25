import { ApiError } from "@/types/api";

interface CardErrorProps {
	error: unknown;
}

export const CardError = ({ error }: CardErrorProps) => {
	const message = error instanceof ApiError ? error.message : "Failed to load.";

	return <div className="text-[12.5px] text-danger">{message}</div>;
};
