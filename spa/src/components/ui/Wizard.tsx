import type { ReactNode } from "react";
import { Check } from "lucide-react";

import { Button } from "./Button";

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
						<Button variant="secondary" onClick={onCancel}>
							{cancelLabel}
						</Button>
					)}
				</div>

				<div className="flex items-center gap-2">
					<Button
						variant="secondary"
						disabled={current === 0}
						onClick={() => onStepChange(current - 1)}
					>
						{backLabel}
					</Button>

					{isLast ? (
						<Button variant="primary" disabled={!canAdvance} onClick={onFinish}>
							{finishLabel}
						</Button>
					) : (
						<Button
							variant="primary"
							disabled={!canAdvance}
							onClick={() => onStepChange(current + 1)}
						>
							{nextLabel}
						</Button>
					)}
				</div>
			</div>
		</div>
	);
};
