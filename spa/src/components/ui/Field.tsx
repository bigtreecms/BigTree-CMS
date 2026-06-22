import type { ReactNode } from "react";

interface FieldProps {
	label: ReactNode;
	error?: string;
	/** Helper text shown under the control (above the error, if any). */
	hint?: ReactNode;
	/** Appends a danger-colored asterisk to the label. */
	required?: boolean;
	children: ReactNode;
	className?: string;
}

/**
 * Label + optional hint/error wrapper shared by the form-control primitives
 * (`TextField`, `SelectField`, …) and any one-off field that needs the same
 * label/error chrome. Renders a `<label>`, so the control inside is associated
 * automatically.
 */
export const Field = ({ label, error, hint, required, children, className }: FieldProps) => {
	return (
		<label className={className ? `block ${className}` : "block"}>
			<span className="mb-1 block text-[12px] font-medium text-text-2">
				{label}
				{required && <span className="text-danger"> *</span>}
			</span>
			{children}

			{hint && <span className="mt-1 block text-[11px] text-text-3">{hint}</span>}

			{error && (
				<span data-field-error className="mt-1 block text-[11.5px] text-danger">
					{error}
				</span>
			)}
		</label>
	);
};
