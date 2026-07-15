export type ProgressBarTone = "accent" | "success";
export type ProgressBarSize = "sm" | "md";

interface ProgressBarProps {
	/**
	 * Layout-only classes for the track — pass a width here, since the track has
	 * none of its own (e.g. `w-24` for an inline upload meter, `w-full` for a
	 * block bar, `flex-1` to fill a flex row).
	 */
	className?: string;
	/**
	 * Accessible name for the bar (sets `aria-label`). Defaults to `"Progress"`;
	 * pass something specific (e.g. `"Upload progress"`) when the context isn't
	 * obvious from surrounding text — a `progressbar` role must have a name.
	 */
	label?: string;
	/** Track height: `sm` (h-1.5, default) or `md` (h-2). */
	size?: ProgressBarSize;
	/** Fill color. Defaults to `accent`; use `success` for a completed bar. */
	tone?: ProgressBarTone;
	/** Completion percent — clamped to the 0–100 range. */
	value: number;
}

/**
 * The shared determinate progress meter — a `bg-surface-2` track with a
 * token-colored fill driven by `value`. The single source of truth for the
 * upload/scan progress bars that used to be hand-rolled (and duplicated as a
 * local `UploadProgress` in the image/upload fields). Token-driven so light and
 * dark both read correctly; carries `role="progressbar"` for assistive tech.
 */

const sizeClassName: Record<ProgressBarSize, string> = {
	sm: "h-1.5",
	md: "h-2",
};

const toneClassName: Record<ProgressBarTone, string> = {
	accent: "bg-accent",
	success: "bg-success",
};

export const ProgressBar = ({
	value,
	size = "sm",
	tone = "accent",
	label = "Progress",
	className,
}: ProgressBarProps) => {
	const percent = Math.max(0, Math.min(100, value));

	return (
		<div
			aria-label={label}
			aria-valuemax={100}
			aria-valuemin={0}
			aria-valuenow={Math.round(percent)}
			className={`relative overflow-hidden rounded-full bg-surface-2 ${sizeClassName[size]}${
				className ? ` ${className}` : ""
			}`}
			role="progressbar"
		>
			<span
				className={`absolute inset-y-0 left-0 rounded-full transition-[width] ${toneClassName[tone]}`}
				style={{ width: `${percent}%` }}
			/>
		</div>
	);
};
