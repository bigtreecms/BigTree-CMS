import type { ReactNode } from "react";

interface FormShellProps {
	/** Optional header bar above the form body. */
	header?: ReactNode;
	children: ReactNode;
	/** Sticky footer (typically Save / Cancel / Delete buttons). */
	footer?: ReactNode;
	/** Apply a max-width container to the form. Defaults to true. */
	bounded?: boolean;
	/** Wrap the inner body in a <form> with this onSubmit. */
	onSubmit?: (event: React.FormEvent) => void;
	className?: string;
}

/**
 * Standard layout for every edit screen: a header bar, a body, and a sticky
 * footer pinned to the bottom of the viewport so primary actions remain
 * reachable while the form is scrolled. Avoids re-implementing this chrome
 * on Pages, Users, Settings, Module entries, etc.
 */
export const FormShell = ({
	header,
	children,
	footer,
	bounded = true,
	onSubmit,
	className,
}: FormShellProps) => {
	const containerClass = `${bounded ? "max-w-3xl" : ""} rounded-xl border border-border bg-surface ${className ?? ""}`;

	const Inner = (
		<>
			{header && (
				<div className="flex items-baseline justify-between border-b border-border bg-surface-2 px-4 py-3 text-[12.5px]">
					{header}
				</div>
			)}

			<div className="p-4">{children}</div>

			{footer && (
				<div className="sticky bottom-0 flex justify-end gap-2 border-t border-border bg-surface-2 px-4 py-3">
					{footer}
				</div>
			)}
		</>
	);

	if (onSubmit) {
		return (
			<form onSubmit={onSubmit} className={containerClass}>
				{Inner}
			</form>
		);
	}

	return <div className={containerClass}>{Inner}</div>;
};
