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
	/** The currently published value (the live content). */
	published: unknown;
	/** The draft value the user is editing. */
	pending: unknown;
	/** True when there is no published counterpart (a never-published new entry). */
	isNew?: boolean;
	/** Heading for the draft column, attributed to its owner. Defaults neutrally. */
	pendingLabel?: string;
	/** The field's BigTree type slug, used to pick a richer renderer. */
	fieldType?: string;
}

export const FieldComparison = ({
	published,
	pending,
	isNew,
	pendingLabel = "Pending draft",
	fieldType,
}: FieldComparisonProps) => (
	<div className="mt-2 grid grid-cols-1 gap-2 rounded-md border border-warn/30 bg-warn/[0.03] p-2 sm:grid-cols-2">
		<ComparisonColumn
			heading="Published"
			value={published}
			emptyLabel={isNew ? "No published version yet" : "Empty"}
			tone="published"
			fieldType={fieldType}
		/>
		<ComparisonColumn
			heading={pendingLabel}
			value={pending}
			emptyLabel="Empty"
			tone="pending"
			fieldType={fieldType}
		/>
	</div>
);

interface ComparisonColumnProps {
	heading: string;
	value: unknown;
	emptyLabel: string;
	tone: "published" | "pending";
	fieldType?: string;
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
			<ComparisonValue value={value} fieldType={fieldType} />
		)}
	</div>
);

interface ComparisonValueProps {
	value: unknown;
	fieldType?: string;
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
	<pre className="max-h-48 overflow-auto whitespace-pre-wrap break-words rounded border border-border bg-surface px-2 py-1.5 font-mono text-[11.5px] leading-5 text-text-2">
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
			href={decoded}
			target="_blank"
			rel="noopener noreferrer"
			className="block truncate rounded border border-border bg-surface px-2 py-1.5 text-[11.5px] text-accent hover:underline"
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
				<a href={src} target="_blank" rel="noopener noreferrer" className="block">
					<img
						src={src}
						alt=""
						className="max-h-32 w-auto max-w-full rounded border border-border bg-surface object-contain"
						onError={() => setFailed(true)}
					/>
				</a>
			)}
		</div>
	);
};
