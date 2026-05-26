import { ChevronLeft, ChevronRight } from "lucide-react";

interface PagerProps {
	page: number;
	totalPages: number;
	onChange: (page: number) => void;
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
				type="button"
				className="flex h-6 min-w-7 items-center justify-center rounded px-1.5 text-text-2 hover:bg-hover disabled:cursor-not-allowed disabled:text-text-4 disabled:hover:bg-transparent"
				disabled={page === 1}
				onClick={() => onChange(page - 1)}
				aria-label="Previous page"
			>
				<ChevronLeft size={13} />
			</button>

			{pages.map((p, idx) => {
				if (p === "...") {
					return (
						<span
							key={`e${idx}`}
							className="flex h-6 min-w-4 items-center justify-center text-text-4"
						>
							…
						</span>
					);
				}

				return (
					<button
						key={p}
						type="button"
						className={`flex h-6 min-w-7 items-center justify-center rounded px-1.5 tabular-nums ${
							p === page
								? "bg-accent font-semibold text-accent-fg"
								: "text-text-2 hover:bg-hover"
						}`}
						onClick={() => onChange(p as number)}
					>
						{p}
					</button>
				);
			})}

			<button
				type="button"
				className="flex h-6 min-w-7 items-center justify-center rounded px-1.5 text-text-2 hover:bg-hover disabled:cursor-not-allowed disabled:text-text-4 disabled:hover:bg-transparent"
				disabled={page === totalPages}
				onClick={() => onChange(page + 1)}
				aria-label="Next page"
			>
				<ChevronRight size={13} />
			</button>
		</div>
	);
};
