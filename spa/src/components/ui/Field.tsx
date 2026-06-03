import type { ReactNode } from "react";

interface FieldProps {
	label: string;
	error?: string;
	children: ReactNode;
	className?: string;
}

export const Field = ({ label, error, children, className }: FieldProps) => {
	return (
		<label className={className ? `block ${className}` : "block"}>
			<span className="mb-1 block text-[12px] font-medium text-text-2">{label}</span>
			{children}

			{error && (
				<span data-field-error className="mt-1 block text-[11.5px] text-danger">
					{error}
				</span>
			)}
		</label>
	);
};
