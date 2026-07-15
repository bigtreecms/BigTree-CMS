import { useState } from "react";

import { formatFieldValue, isEmptyValue, prettyJsonValue } from "@/lib/fieldComparison";
import { expandImageUrl } from "@/lib/imageUrl";

/**
 * Side-by-side "published vs. pending" comparison for a single form field.
 * Rendered inline beneath a field when the user expands its "Compare" toggle.
 *
 * Rendering adapts to the field type so common core types read well:
 *   - image                → preview thumbnail
 *   - upload / link        → decoded, clickable URL ({staticroot}/{wwwroot} expanded)
 *   - media-gallery        → pretty-printed JSON
 *   - everything else      → the value formatted as text (objects as JSON)
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
}: FieldComparisonProps) => (
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
