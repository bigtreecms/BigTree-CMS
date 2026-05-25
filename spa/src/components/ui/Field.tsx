import type { ReactNode } from "react";

interface FieldProps {
	label: string;
	error?: string;
	children: ReactNode;
}

export const Field = ({ label, error, children }: FieldProps) => {
	return (
		<label className="block">
			<span className="mb-1 block text-[12px] font-medium text-text-2">{label}</span>
			{children}

			{error && <span className="mt-1 block text-[11.5px] text-danger">{error}</span>}
		</label>
	);
};
