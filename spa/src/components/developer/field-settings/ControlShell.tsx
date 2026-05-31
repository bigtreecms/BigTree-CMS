import type { ReactNode } from "react";

interface ControlShellProps {
	label?: string;
	hint?: string;
	note?: string;
	required?: boolean;
	children: ReactNode;
}

/**
 * Shared label / hint / note wrapper so every simple control renders with the
 * same spacing as the rest of the resource designer.
 */
export const ControlShell = ({ label, hint, note, required, children }: ControlShellProps) => (
	<label className="block">
		{label && (
			<span className="mb-1 block text-[11.5px] font-medium text-text-2">
				{label}
				{required && <span className="text-danger"> *</span>}
				{hint && <span className="ml-1 font-normal text-text-3">{hint}</span>}
			</span>
		)}
		{children}
		{note && <p className="mt-1 text-[11px] leading-relaxed text-text-3">{note}</p>}
	</label>
);
