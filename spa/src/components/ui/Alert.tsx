import type { HTMLAttributes, ReactNode } from "react";

/**
 * The lighter inline notice banner — a save-failure summary, a field-level error,
 * a legacy-mode heads-up. The single source of truth for the bordered `rounded-md`
 * message box that used to be copy-pasted (with margin/size drift) into 20+ files.
 *
 * Tones map to the design tokens (never raw colors), so it reads correctly in light
 * + dark mode. `danger` carries `role="alert"` (announced assertively for save
 * failures); the calmer tones use `role="status"`. Keep the surrounding margin in
 * `className` — the base owns none. For the heavier query-failure box (status / code /
 * request_id from an `ApiError`) use `ErrorPanel` instead.
 */
export type AlertTone = "danger" | "warn" | "success" | "info";

const TONE_CLASS: Record<AlertTone, string> = {
	danger: "border-danger/40 bg-danger/5 text-danger",
	warn: "border-warn/40 bg-warn-bg text-warn",
	success: "border-success/40 bg-success-bg text-success",
	info: "border-accent/30 bg-accent-soft text-text-2",
};

interface AlertProps extends Omit<HTMLAttributes<HTMLDivElement>, "title"> {
	children: ReactNode;
	/** Layout-only classes the caller still owns — the spacing (`mb-3`, `mb-2`). */
	className?: string;
	/** Leading icon node; the caller sizes it (e.g. `<AlertTriangle size={13} />`). */
	icon?: ReactNode;
	/** Render the body in `font-mono` — the code/build-error variant. */
	mono?: boolean;
	/** Bold first line above the body (e.g. "Errors — fix these before installing"). */
	title?: ReactNode;
	/** Token-mapped tone. Defaults to `danger`. */
	tone?: AlertTone;
}

export const Alert = ({
	children,
	tone = "danger",
	icon,
	title,
	mono = false,
	className = "",
	...rest
}: AlertProps) => {
	const role = tone === "danger" ? "alert" : "status";

	if (title) {
		return (
			<div
				className={`rounded-md border px-3 py-2 text-[12.5px] ${TONE_CLASS[tone]}${
					className ? ` ${className}` : ""
				}`}
				role={role}
				{...rest}
			>
				<div
					className={`flex items-center gap-1.5 font-semibold${children ? " mb-1.5" : ""}`}
				>
					{icon}
					{title}
				</div>
				{mono ? <div className="font-mono">{children}</div> : children}
			</div>
		);
	}

	return (
		<div
			className={`rounded-md border px-3 py-2 text-[12.5px] ${TONE_CLASS[tone]}${
				icon ? " flex items-center gap-1.5" : ""
			}${mono ? " font-mono" : ""}${className ? ` ${className}` : ""}`}
			role={role}
			{...rest}
		>
			{icon}
			{children}
		</div>
	);
};
