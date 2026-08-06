import { useMemo } from "react";

import { toDiffRows, type FieldDiff } from "@/lib/textDiff";

/**
 * Renders the hunks {@link buildFieldDiff} produced, in the app's own tokens:
 * additions in the success tint, removals in the danger tint, struck through.
 *
 * Two layouts, picked by the diff's mode —
 *   - words → one flowing block of prose with inline <ins>/<del> runs, which is
 *     how a person reads a paragraph that changed in two places.
 *   - lines → a gutter-marked list, with unchanged stretches collapsed so the
 *     edited row in a fifty-row matrix isn't buried.
 */

interface TextDiffProps {
	diff: FieldDiff;
}

export const TextDiff = ({ diff }: TextDiffProps) => {
	if (diff.identical) {
		return null;
	}

	if (diff.mode === "words") {
		return <WordDiff diff={diff} />;
	}

	return <LineDiff diff={diff} />;
};

const WordDiff = ({ diff }: TextDiffProps) => (
	<p className="max-h-64 overflow-auto whitespace-pre-wrap wrap-break-word rounded border border-border bg-surface px-2 py-1.5 text-[12.5px]/6 text-text-2">
		{diff.changes.map((change, index) => {
			if (change.added) {
				return (
					<ins
						className="rounded-[2px] bg-success-bg px-0.5 text-success no-underline"
						key={index}
					>
						{change.value}
					</ins>
				);
			}

			if (change.removed) {
				return (
					<del className="rounded-[2px] bg-danger-bg px-0.5 text-danger" key={index}>
						{change.value}
					</del>
				);
			}

			return <span key={index}>{change.value}</span>;
		})}
	</p>
);

const LineDiff = ({ diff }: TextDiffProps) => {
	const rows = useMemo(() => toDiffRows(diff.changes), [diff.changes]);

	return (
		<div className="max-h-64 overflow-auto rounded border border-border bg-surface font-mono text-[11.5px]/5">
			{rows.map((row, index) => {
				if (row.kind === "collapsed") {
					return (
						<div
							className="border-y border-border bg-surface-2 px-2 py-0.5 text-center text-[10.5px] text-text-3"
							key={index}
						>
							{row.count} unchanged {row.count === 1 ? "line" : "lines"}
						</div>
					);
				}

				return (
					<div
						className={`flex gap-1.5 px-2 ${
							row.type === "added"
								? "bg-success-bg text-success"
								: row.type === "removed"
									? "bg-danger-bg text-danger"
									: "text-text-2"
						}`}
						key={index}
					>
						<span aria-hidden className="select-none opacity-60">
							{row.type === "added" ? "+" : row.type === "removed" ? "−" : " "}
						</span>
						<span className="whitespace-pre-wrap wrap-break-word">
							{row.text || " "}
						</span>
					</div>
				);
			})}
		</div>
	);
};

interface DiffStatsProps {
	diff: FieldDiff;
}

/** "+12 −4", in the unit the diff actually counted. */
export const DiffStats = ({ diff }: DiffStatsProps) => {
	const unit = diff.mode === "lines" ? "line" : "word";

	return (
		<span className="font-mono text-[10.5px] text-text-3">
			<span className="text-success">
				+{diff.added} {diff.added === 1 ? unit : `${unit}s`}
			</span>{" "}
			<span className="text-danger">−{diff.removed}</span>
		</span>
	);
};
