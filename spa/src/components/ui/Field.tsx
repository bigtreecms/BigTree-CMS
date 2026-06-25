import type { ReactNode } from "react";

export type FieldSize = "sm" | "md";

/** The danger-colored required-field asterisk, shared by every label that marks a field required. */
export const RequiredMarker = () => <span className="text-danger"> *</span>;

interface FieldProps {
	/** Visible label. Optional — omit for an unlabeled wrapper (e.g. a control that only needs a hint). */
	label?: ReactNode;
	error?: string;
	/** Helper text shown under the control (above the error, if any). */
	hint?: ReactNode;
	/**
	 * Subtle helper rendered inline after the label (`font-normal text-text-3`) —
	 * the resource-designer treatment, where a short hint sits on the label line
	 * rather than below the control.
	 */
	inlineHint?: ReactNode;
	/** Appends a danger-colored asterisk to the label. */
	required?: boolean;
	/** Label text size: `md` (default, `text-[12px]`) or `sm` (`text-[11.5px]`, the denser designer controls). */
	size?: FieldSize;
	children: ReactNode;
	className?: string;
}

/**
 * Label + optional hint/error wrapper shared by the form-control primitives
 * (`TextField`, `SelectField`, …) and any one-off field that needs the same
 * label/error chrome. Renders a `<label>`, so the control inside is associated
 * automatically. The single source of truth for the label/hint/error layout —
 * `ControlShell` (the resource designer's wrapper) composes this with
 * `size="sm"` + an `inlineHint`.
 */
const labelSizeClass: Record<FieldSize, string> = {
	sm: "text-[11.5px]",
	md: "text-[12px]",
};

export const Field = ({
	label,
	error,
	hint,
	inlineHint,
	required,
	size = "md",
	children,
	className,
}: FieldProps) => {
	return (
		<label className={className ? `block ${className}` : "block"}>
			{label && (
				<span className={`mb-1 block font-medium text-text-2 ${labelSizeClass[size]}`}>
					{label}
					{required && <RequiredMarker />}
					{inlineHint && (
						<span className="ml-1 font-normal text-text-3">{inlineHint}</span>
					)}
				</span>
			)}
			{children}

			{hint && (
				<span className="mt-1 block text-[11px] leading-relaxed text-text-3">{hint}</span>
			)}

			{error && (
				<span data-field-error className="mt-1 block text-[11.5px] text-danger">
					{error}
				</span>
			)}
		</label>
	);
};
