/**
 * Placeholder screen for not-yet-implemented routes. As we port each prototype
 * screen, the corresponding route swaps Placeholder for the real component.
 */
interface PlaceholderProps {
	title: string;
	note?: string;
}

export function Placeholder({ title, note }: PlaceholderProps) {
	return (
		<div className="mx-auto max-w-screen-2xl px-6 py-6">
			<h1 className="text-[20px] font-semibold tracking-[-0.01em]">
				{title}
			</h1>
			<p className="mt-1 text-[13px] text-text-3">
				{note ??
					"This screen hasn't been built yet — it'll match the prototype's design."}
			</p>
		</div>
	);
}
