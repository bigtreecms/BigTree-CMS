import type { ReactNode } from "react";

import { CardFooter, CardHeader } from "./Card";

interface FormShellProps {
	/** Apply a max-width container to the form. Defaults to true. */
	bounded?: boolean;
	children: ReactNode;
	className?: string;
	/** Sticky footer (typically Save / Cancel / Delete buttons). */
	footer?: ReactNode;
	/** Optional header bar above the form body. */
	header?: ReactNode;
	/** Wrap the inner body in a <form> with this onSubmit. */
	onSubmit?: (event: React.FormEvent) => void;
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
				<CardHeader className="flex items-baseline justify-between rounded-t-xl text-[12.5px]">
					{header}
				</CardHeader>
			)}

			<div className="p-4">{children}</div>

			{footer && (
				<CardFooter sticky className="rounded-b-xl">
					{footer}
				</CardFooter>
			)}
		</>
	);

	if (onSubmit) {
		return (
			<form className={containerClass} onSubmit={onSubmit}>
				{Inner}
			</form>
		);
	}

	return <div className={containerClass}>{Inner}</div>;
};
