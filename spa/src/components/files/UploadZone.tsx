import { useEffect, useRef, useState, type DragEvent } from "react";
import { CheckCircle2, Upload as UploadIcon, X, XCircle } from "lucide-react";

import { useUploads, type UploadItem } from "@/hooks/useUploads";
import { UPLOAD_PATH } from "@/api/endpoints/resources";
import type { ResourceDetail } from "@/api/endpoints/resources";
import { formatBytes } from "@/lib/bytes";

interface UploadZoneProps {
	/** Target folder id; sent alongside each upload as `folder=`. 0 = home. */
	folderId: number;
	/** Called once per successful upload, with the API's response data. */
	onUploaded?: (resource: ResourceDetail) => void;
}

/**
 * Drag-and-drop + click-to-pick upload zone for the Files browser.
 *
 * Sits at the top of the listing while a folder has publisher access. Each
 * dropped/picked file goes through `useUploads.enqueue` so the UI gets real
 * upload progress (XHR, not fetch). Completed items fire `onUploaded` once,
 * driven by a ref-tracked set so re-renders don't double-fire.
 *
 * The "done" item rows linger so the user has a record of what just landed;
 * a Clear button wipes finished ones without affecting in-flight uploads.
 */
export const UploadZone = ({ folderId, onUploaded }: UploadZoneProps) => {
	const { items, enqueue, cancel } = useUploads();
	const [isOver, setIsOver] = useState(false);
	const inputRef = useRef<HTMLInputElement>(null);
	const firedRef = useRef(new Set<number>());

	useEffect(() => {
		if (!onUploaded) {
			return;
		}

		const newlyDone = items.filter(
			(it) => it.status === "done" && !firedRef.current.has(it.id)
		);

		if (newlyDone.length === 0) {
			return;
		}

		for (const it of newlyDone) {
			firedRef.current.add(it.id);

			if (it.result) {
				onUploaded(it.result as ResourceDetail);
			}
		}
	}, [items, onUploaded]);

	const startUploads = (files: FileList | File[]) => {
		enqueue(files, {
			path: UPLOAD_PATH,
			extra: { folder: folderId },
		});
	};

	const handleDrop = (event: DragEvent<HTMLDivElement>) => {
		event.preventDefault();
		setIsOver(false);

		if (event.dataTransfer.files.length > 0) {
			startUploads(event.dataTransfer.files);
		}
	};

	const handleDragOver = (event: DragEvent<HTMLDivElement>) => {
		event.preventDefault();

		if (!isOver) {
			setIsOver(true);
		}
	};

	const handleDragLeave = (event: DragEvent<HTMLDivElement>) => {
		// Only clear when leaving the zone itself, not crossing into a child.
		if (event.currentTarget.contains(event.relatedTarget as Node | null)) {
			return;
		}

		setIsOver(false);
	};

	const visibleItems = items.filter((it) => it.status !== "canceled");

	return (
		<div className="mb-4">
			<div
				className={`rounded-lg border-2 border-dashed px-4 py-5 text-center transition-colors ${
					isOver
						? "border-accent bg-accent-soft/40"
						: "border-border bg-surface-2 hover:bg-hover"
				}`}
				onDrop={handleDrop}
				onDragOver={handleDragOver}
				onDragLeave={handleDragLeave}
			>
				<UploadIcon size={20} className="mx-auto mb-1 text-text-3" />
				<div className="text-[13px] text-text-2">
					<button
						type="button"
						className="font-medium text-accent hover:underline"
						onClick={() => inputRef.current?.click()}
					>
						Choose files
					</button>{" "}
					or drag and drop them here.
				</div>
				<div className="mt-0.5 text-[11.5px] text-text-3">
					Up to 256 MB per file. Images, videos, documents.
				</div>

				<input
					ref={inputRef}
					type="file"
					multiple
					className="hidden"
					onChange={(e) => {
						if (e.target.files && e.target.files.length > 0) {
							startUploads(e.target.files);
							e.target.value = ""; // allow re-picking the same file
						}
					}}
				/>
			</div>

			{visibleItems.length > 0 && (
				<ul className="mt-2 space-y-1.5 rounded-lg border border-border bg-surface px-3 py-2">
					{visibleItems.map((item) => (
						<UploadRow key={item.id} item={item} onCancel={() => cancel(item.id)} />
					))}
				</ul>
			)}
		</div>
	);
};

interface UploadRowProps {
	item: UploadItem;
	onCancel: () => void;
}

const UploadRow = ({ item, onCancel }: UploadRowProps) => {
	const inFlight = item.status === "uploading" || item.status === "pending";
	const failed = item.status === "error";
	const done = item.status === "done";

	return (
		<li className="grid grid-cols-[minmax(0,1fr)_140px_24px] items-center gap-3 text-[12.5px]">
			<div className="min-w-0">
				<div className="truncate text-text" title={item.file.name}>
					{item.file.name}
				</div>
				<div className="flex items-center gap-2 text-[11px] text-text-3">
					<span className="tabular-nums">{formatBytes(item.file.size)}</span>
					{failed && <span className="text-danger">{item.error || "Upload failed"}</span>}
				</div>
			</div>

			<div className="flex items-center gap-2">
				{inFlight && (
					<>
						<div className="relative h-1.5 flex-1 overflow-hidden rounded-full bg-surface-2">
							<div
								className="absolute inset-y-0 left-0 bg-accent transition-[width]"
								style={{ width: `${item.progress}%` }}
							/>
						</div>
						<span className="w-9 text-right tabular-nums text-[11px] text-text-3">
							{item.progress}%
						</span>
					</>
				)}
				{done && (
					<span className="ml-auto inline-flex items-center gap-1 text-[11px] text-success">
						<CheckCircle2 size={13} />
						Uploaded
					</span>
				)}
				{failed && (
					<span className="ml-auto inline-flex items-center gap-1 text-[11px] text-danger">
						<XCircle size={13} />
						Error
					</span>
				)}
			</div>

			<button
				type="button"
				className="rounded p-1 text-text-3 hover:bg-hover hover:text-text"
				onClick={onCancel}
				aria-label="Cancel upload"
				disabled={done || failed}
			>
				<X size={13} />
			</button>
		</li>
	);
};
