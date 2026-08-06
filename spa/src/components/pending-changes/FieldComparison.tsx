import { useMemo, useState } from "react";

import { formatFieldValue, isEmptyValue, prettyJsonValue } from "@/lib/fieldComparison";
import { buildFieldDiff, type HtmlDiffView } from "@/lib/textDiff";
import { expandImageUrl } from "@/lib/imageUrl";

import { DiffStats, TextDiff } from "./TextDiff";

/**
 * "Published vs. pending" comparison for a single form field. Rendered inline
 * beneath a field when the user expands its "Compare" toggle.
 *
 * Two presentations, chosen by field type:
 *
 *   - Text-like fields (text, textarea, html, the composite JSON types, and any
 *     untyped column holding a string) get a real diff — see lib/textDiff.ts.
 *     Reading two columns of body copy to find the sentence that moved is not
 *     something a publisher should have to do.
 *   - Everything else keeps the side-by-side columns, where the value's own
 *     renderer says more than a diff would:
 *       image                → preview thumbnail
 *       upload / link        → decoded, clickable URL ({staticroot}/{wwwroot})
 *       media-gallery/matrix → pretty-printed JSON (only when a diff isn't
 *                              possible, e.g. a brand-new entry)
 *       everything else      → the value formatted as text
 *
 * A never-published draft (`isNew`) has no baseline to diff against, so it also
 * keeps the columns — every word would be an insertion.
 */

interface FieldComparisonProps {
	/** The field's BigTree type slug, used to pick a richer renderer. */
	fieldType?: string;
	/** True when there is no published counterpart (a never-published new entry). */
	isNew?: boolean;
	/** The draft value the user is editing. */
	pending: unknown;
	/** Heading for the draft column, attributed to its owner. Defaults neutrally. */
	pendingLabel?: string;
	/** The currently published value (the live content). */
	published: unknown;
}

export const FieldComparison = ({
	published,
	pending,
	isNew,
	pendingLabel = "Pending draft",
	fieldType,
}: FieldComparisonProps) => {
	const [htmlView, setHtmlView] = useState<HtmlDiffView>("text");
	const diff = useMemo(
		() => (isNew ? null : buildFieldDiff(published, pending, fieldType, htmlView)),
		[isNew, published, pending, fieldType, htmlView]
	);

	if (!diff) {
		return (
			<div className="mt-2 grid grid-cols-1 gap-2 rounded-md border border-warn/30 bg-warn/3 p-2 sm:grid-cols-2">
				<ComparisonColumn
					emptyLabel={isNew ? "No published version yet" : "Empty"}
					fieldType={fieldType}
					heading="Published"
					tone="published"
					value={published}
				/>
				<ComparisonColumn
					emptyLabel="Empty"
					fieldType={fieldType}
					heading={pendingLabel}
					tone="pending"
					value={pending}
				/>
			</div>
		);
	}

	return (
		<div className="mt-2 rounded-md border border-warn/30 bg-warn/3 p-2">
			<div className="mb-1.5 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
				<div className="text-[10.5px] font-semibold uppercase tracking-[0.04em] text-text-3">
					Published <span aria-hidden>→</span>{" "}
					<span className="text-warn">{pendingLabel}</span>
				</div>
				<div className="flex items-center gap-2">
					{fieldType === "html" && (
						<HtmlViewToggle value={htmlView} onChange={setHtmlView} />
					)}
					{!diff.identical && <DiffStats diff={diff} />}
				</div>
			</div>

			{diff.identical ? (
				<div className="rounded border border-border bg-surface px-2 py-1.5 text-[12px] italic text-text-3">
					{fieldType === "html" && htmlView === "text"
						? "The text reads the same — switch to Markup to see what changed."
						: "No differences to show."}
				</div>
			) : (
				<TextDiff diff={diff} />
			)}
		</div>
	);
};

interface HtmlViewToggleProps {
	onChange: (next: HtmlDiffView) => void;
	value: HtmlDiffView;
}

/**
 * Text vs. Markup for an HTML field. Text is the default because it answers the
 * question a publisher is actually asking ("what will the page say?"); Markup is
 * there for the changes text can't show — a link retargeted, a heading level
 * changed, an image swapped.
 */
const HtmlViewToggle = ({ value, onChange }: HtmlViewToggleProps) => (
	<div className="flex overflow-hidden rounded border border-border" role="group">
		{(["text", "markup"] as const).map((option) => (
			<button
				aria-pressed={value === option}
				className={`px-1.5 py-0.5 text-[10.5px] capitalize ${
					value === option
						? "bg-surface-3 text-text-2"
						: "bg-surface text-text-3 hover:text-text-2"
				}`}
				key={option}
				type="button"
				onClick={() => onChange(option)}
			>
				{option}
			</button>
		))}
	</div>
);

interface ComparisonColumnProps {
	emptyLabel: string;
	fieldType?: string;
	heading: string;
	tone: "published" | "pending";
	value: unknown;
}

const ComparisonColumn = ({
	heading,
	value,
	emptyLabel,
	tone,
	fieldType,
}: ComparisonColumnProps) => (
	<div>
		<div
			className={`mb-1 text-[10.5px] font-semibold uppercase tracking-[0.04em] ${
				tone === "pending" ? "text-warn" : "text-text-3"
			}`}
		>
			{heading}
		</div>
		{isEmptyValue(value) ? (
			<div className="text-[12px] italic text-text-3">{emptyLabel}</div>
		) : (
			<ComparisonValue fieldType={fieldType} value={value} />
		)}
	</div>
);

interface ComparisonValueProps {
	fieldType?: string;
	value: unknown;
}

// Keep the types branched on here in step with OPAQUE_TYPES in lib/textDiff.ts —
// a type with a widget of its own should not be handed to the differ.
const ComparisonValue = ({ value, fieldType }: ComparisonValueProps) => {
	if (fieldType === "image") {
		return <ImagePreview value={value} />;
	}

	if (fieldType === "upload" || fieldType === "link") {
		return <DecodedUrl value={value} />;
	}

	if (fieldType === "media-gallery" || fieldType === "matrix") {
		return <CodeBlock text={prettyJsonValue(value)} />;
	}

	return <CodeBlock text={formatFieldValue(value) ?? ""} />;
};

const CodeBlock = ({ text }: { text: string }) => (
	<pre className="max-h-48 overflow-auto whitespace-pre-wrap wrap-break-word rounded border border-border bg-surface px-2 py-1.5 font-mono text-[11.5px]/5 text-text-2">
		{text}
	</pre>
);

/** Decode {staticroot}/{wwwroot} tokens; render as a clickable link when it resolves to a URL/path. */
const DecodedUrl = ({ value }: { value: unknown }) => {
	const raw = typeof value === "string" ? value : "";
	const decoded = expandImageUrl(raw) || (raw.length > 0 ? raw : formatFieldValue(value) || "");
	const linkable = /^(https?:|\/)/.test(decoded);

	if (!linkable) {
		return <CodeBlock text={decoded} />;
	}

	return (
		<a
			className="block truncate rounded border border-border bg-surface px-2 py-1.5 text-[11.5px] text-accent hover:underline"
			href={decoded}
			rel="noopener noreferrer"
			target="_blank"
			title={decoded}
		>
			{decoded}
		</a>
	);
};

/** Image preview with the decoded path beneath; falls back to the path if the image won't load. */
const ImagePreview = ({ value }: { value: unknown }) => {
	const [failed, setFailed] = useState(false);
	const src = expandImageUrl(value);

	if (!src) {
		return <CodeBlock text={formatFieldValue(value) ?? ""} />;
	}

	return (
		<div className="space-y-1">
			{failed ? (
				<CodeBlock text={src} />
			) : (
				<a
					aria-label="Open image preview in a new tab"
					className="block"
					href={src}
					rel="noopener noreferrer"
					target="_blank"
				>
					<img
						alt=""
						className="max-h-32 w-auto max-w-full rounded border border-border bg-surface object-contain"
						src={src}
						onError={() => setFailed(true)}
					/>
				</a>
			)}
		</div>
	);
};
