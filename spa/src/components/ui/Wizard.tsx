import type { ReactNode } from "react";
import { Check } from "lucide-react";

export interface WizardStep {
	key: string;
	title: string;
	description?: string;
	content: ReactNode;
	/** Disable navigation forward from this step (e.g. validation not satisfied). */
	canAdvance?: boolean;
}

interface WizardProps {
	steps: WizardStep[];
	current: number;
	onStepChange: (index: number) => void;
	onCancel?: () => void;
	onFinish?: () => void;
	finishLabel?: string;
	nextLabel?: string;
	backLabel?: string;
	cancelLabel?: string;
}

/**
 * Multi-step shell with progress dots + Back/Next/Finish controls. Step
 * content is controlled — the host page is responsible for validating the
 * current step before letting the user advance (via `steps[i].canAdvance`).
 *
 * Used by the module designer (Phase 8) and the system upgrade flow.
 */
export const Wizard = ({
	steps,
	current,
	onStepChange,
	onCancel,
	onFinish,
	finishLabel = "Finish",
	nextLabel = "Next",
	backLabel = "Back",
	cancelLabel = "Cancel",
}: WizardProps) => {
	const step = steps[current];
	const isLast = current === steps.length - 1;
	const canAdvance = step?.canAdvance ?? true;

	return (
		<div className="rounded-xl border border-border bg-surface">
			<div className="border-b border-border bg-surface-2 px-5 py-4">
				<ol className="flex items-center gap-3">
					{steps.map((s, idx) => {
						const completed = idx < current;
						const active = idx === current;

						return (
							<li key={s.key} className="flex flex-1 items-center gap-3">
								<button
									type="button"
									className="flex items-center gap-2 text-left"
									disabled={!completed}
									onClick={() => {
										if (completed) {
											onStepChange(idx);
										}
									}}
								>
									<span
										className={`flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold tabular-nums ${
											completed
												? "bg-accent text-accent-fg"
												: active
													? "bg-accent-soft text-accent"
													: "bg-surface-3 text-text-3"
										}`}
									>
										{completed ? <Check size={12} /> : idx + 1}
									</span>

									<span
										className={`text-[12.5px] font-medium ${
											active ? "text-text" : "text-text-3"
										}`}
									>
										{s.title}
									</span>
								</button>

								{idx < steps.length - 1 && (
									<span className="h-px flex-1 bg-border" aria-hidden="true" />
								)}
							</li>
						);
					})}
				</ol>
			</div>

			<div className="px-5 py-5">
				{step?.description && (
					<p className="mb-4 text-[13px] text-text-2">{step.description}</p>
				)}

				{step?.content}
			</div>

			<div className="flex items-center justify-between border-t border-border bg-surface-2 px-5 py-3">
				<div>
					{onCancel && (
						<button
							type="button"
							className="rounded-md border border-border px-3 py-1.5 text-[12.5px] hover:bg-hover"
							onClick={onCancel}
						>
							{cancelLabel}
						</button>
					)}
				</div>

				<div className="flex items-center gap-2">
					<button
						type="button"
						disabled={current === 0}
						className="rounded-md border border-border px-3 py-1.5 text-[12.5px] disabled:opacity-50 hover:bg-hover"
						onClick={() => onStepChange(current - 1)}
					>
						{backLabel}
					</button>

					{isLast ? (
						<button
							type="button"
							disabled={!canAdvance}
							className="rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-60 hover:bg-accent-hover"
							onClick={onFinish}
						>
							{finishLabel}
						</button>
					) : (
						<button
							type="button"
							disabled={!canAdvance}
							className="rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg disabled:opacity-60 hover:bg-accent-hover"
							onClick={() => onStepChange(current + 1)}
						>
							{nextLabel}
						</button>
					)}
				</div>
			</div>
		</div>
	);
};
