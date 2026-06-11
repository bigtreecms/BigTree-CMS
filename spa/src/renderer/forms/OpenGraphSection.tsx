/** The Open Graph value carried by pages and module entries. */
export interface OpenGraphValue {
	title?: string;
	description?: string;
	type?: string;
	image?: string;
}

interface OpenGraphSectionProps {
	value: OpenGraphValue;
	onChange: (next: OpenGraphValue) => void;
	disabled?: boolean;
}

const INPUT =
	"w-full rounded-md border border-border bg-surface px-2.5 py-1.5 text-[13px] outline-none transition-colors focus:border-accent";

const LABEL = "mb-1.5 block text-[11.5px] font-medium text-text-2";

/**
 * Open Graph metadata inputs for module forms with `open_graph` enabled —
 * the module-form counterpart of the page editor's Sharing tab (same four
 * fields, packed into `__open_graph__` on submit by FormRenderer).
 */
export const OpenGraphSection = ({ value, onChange, disabled }: OpenGraphSectionProps) => {
	const patch = (next: Partial<OpenGraphValue>) => {
		onChange({ ...value, ...next });
	};

	return (
		<div className="mt-5 border-t border-border pt-4">
			<h3 className="mb-3 text-[12.5px] font-semibold uppercase tracking-[0.06em] text-text-3">
				Open Graph
			</h3>

			<div className="flex flex-col gap-[14px]">
				<div>
					<span className={LABEL}>
						Title{" "}
						<span className="font-normal text-text-3">
							(defaults to the entry's title if left empty)
						</span>
					</span>
					<input
						className={INPUT}
						value={value.title ?? ""}
						onChange={(e) => patch({ title: e.target.value })}
						disabled={disabled}
					/>
				</div>

				<div>
					<span className={LABEL}>Description</span>
					<input
						className={INPUT}
						value={value.description ?? ""}
						onChange={(e) => patch({ description: e.target.value })}
						disabled={disabled}
					/>
				</div>

				<div className="grid grid-cols-1 gap-[14px] md:grid-cols-2 md:gap-x-[22px]">
					<div>
						<span className={LABEL}>Type</span>
						<select
							className={INPUT}
							value={value.type ?? ""}
							onChange={(e) => patch({ type: e.target.value })}
							disabled={disabled}
						>
							<option value="">—</option>
							<option value="website">website</option>
							<option value="article">article</option>
							<option value="profile">profile</option>
							<option value="video.movie">video.movie</option>
						</select>
					</div>
					<div>
						<span className={LABEL}>
							Image <span className="font-normal text-text-3">(min 1200×630)</span>
						</span>
						<input
							className={INPUT}
							value={value.image ?? ""}
							onChange={(e) => patch({ image: e.target.value })}
							placeholder="https://"
							disabled={disabled}
						/>
					</div>
				</div>
			</div>
		</div>
	);
};
