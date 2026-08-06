import { ChevronLeft, ChevronRight } from "lucide-react";

export interface PagerProps {
	onChange: (page: number) => void;
	page: number;
	totalPages: number;
}

/**
 * Reusable pagination control matching the design prototype.
 * Used by Users, Settings, and other list views.
 */
export const Pager = ({ page, totalPages, onChange }: PagerProps) => {
	if (totalPages <= 1) {
		return null;
	}

	const pages: (number | "...")[] = [];

	if (totalPages <= 7) {
		for (let i = 1; i <= totalPages; i++) {
			pages.push(i);
		}
	} else {
		pages.push(1);

		if (page > 3) {
			pages.push("...");
		}

		for (let i = Math.max(2, page - 1); i <= Math.min(totalPages - 1, page + 1); i++) {
			pages.push(i);
		}

		if (page < totalPages - 2) {
			pages.push("...");
		}

		pages.push(totalPages);
	}

	return (
		<div className="inline-flex items-center gap-0.5 rounded-md border border-border bg-surface p-0.5 text-[12px]">
			<button
				aria-label="Previous page"
				className="flex h-6 min-w-7 items-center justify-center rounded px-1.5 text-text-2 transition hover:bg-hover active:scale-[0.96] disabled:cursor-not-allowed disabled:text-text-4 disabled:hover:bg-transparent disabled:active:scale-100"
				disabled={page === 1}
				type="button"
				onClick={() => onChange(page - 1)}
			>
				<ChevronLeft size={13} />
			</button>

			{pages.map((p, idx) => {
				if (p === "...") {
					return (
						<span
							className="flex h-6 min-w-4 items-center justify-center text-text-4"
							key={`e${idx}`}
						>
							…
						</span>
					);
				}

				return (
					<button
						className={`flex h-6 min-w-7 items-center justify-center rounded px-1.5 tabular-nums transition active:scale-[0.96] ${
							p === page
								? "bg-accent font-semibold text-accent-fg"
								: "text-text-2 hover:bg-hover"
						}`}
						key={p}
						type="button"
						onClick={() => onChange(p as number)}
					>
						{p}
					</button>
				);
			})}

			<button
				aria-label="Next page"
				className="flex h-6 min-w-7 items-center justify-center rounded px-1.5 text-text-2 transition hover:bg-hover active:scale-[0.96] disabled:cursor-not-allowed disabled:text-text-4 disabled:hover:bg-transparent disabled:active:scale-100"
				disabled={page === totalPages}
				type="button"
				onClick={() => onChange(page + 1)}
			>
				<ChevronRight size={13} />
			</button>
		</div>
	);
};
