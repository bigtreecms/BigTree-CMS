import { useRef, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { AlertTriangle, CheckCircle2, ChevronLeft, Package, Upload } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { HeaderBtn } from "@/components/ui/HeaderBtn";
import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import {
	extensionsApi,
	type ExtensionInstallPreview,
	type ExtensionInstallResult,
} from "@/api/endpoints/extensions";
import { ApiError } from "@/types/api";
import { toast } from "@/lib/toast";
import { sanitizeHtml } from "@/lib/html";

/**
 * Two-step extension installer (legacy install/{unpack,process}.php):
 *   1. Upload a .zip → server stages it and reports what installing would do.
 *   2. Review warnings/errors → commit, which runs the install SQL + install.php.
 */
export const ExtensionInstall = () => {
	const navigate = useNavigate();
	const queryClient = useQueryClient();
	const inputRef = useRef<HTMLInputElement>(null);

	const [file, setFile] = useState<File | null>(null);
	const [preview, setPreview] = useState<ExtensionInstallPreview | null>(null);
	const [result, setResult] = useState<ExtensionInstallResult | null>(null);
	const [error, setError] = useState<string | null>(null);

	const unpackMutation = useMutation({
		mutationFn: (f: File) => extensionsApi.installUnpack(f),
		onSuccess: (p) => {
			setPreview(p);
			setError(null);
		},
		onError: (err) => {
			setPreview(null);
			setError(
				err instanceof ApiError && err.message ? err.message : "Could not read the package"
			);
		},
	});

	const processMutation = useMutation({
		mutationFn: () => extensionsApi.installProcess(),
		onSuccess: (r) => {
			setResult(r);
			queryClient.invalidateQueries({ queryKey: ["extensions"] });
			toast.success("Extension installed");
		},
		onError: (err) => {
			setError(err instanceof ApiError && err.message ? err.message : "Install failed");
		},
	});

	const reset = () => {
		setFile(null);
		setPreview(null);
		setResult(null);
		setError(null);
	};

	return (
		<div className="mx-auto max-w-screen-md px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Developer", to: "/developer" },
					{ label: "Extensions", to: "/developer/extensions" },
					{ label: "Install" },
				]}
			/>

			<PageHead
				title="Install extension"
				sub="Upload an extension package (.zip). You'll review what it changes before it's installed."
				actions={
					<HeaderBtn icon={<ChevronLeft size={13} />} to="/developer/extensions">
						Back
					</HeaderBtn>
				}
			/>

			<DeveloperSectionNav />

			{error && (
				<div className="mb-3 rounded-md border border-danger/40 bg-danger/5 px-3 py-2 text-[12.5px] text-danger">
					{error}
				</div>
			)}

			{result ? (
				<div className="space-y-4 rounded-xl border border-border bg-surface p-5">
					<div className="flex items-center gap-2 text-success">
						<CheckCircle2 size={18} />
						<span className="text-[14px] font-semibold">Installed “{result.id}”</span>
					</div>
					{result.output ? (
						<div>
							<h3 className="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.06em] text-text-3">
								Installer output
							</h3>
							<div
								className="overflow-x-auto rounded-lg border border-border bg-surface-2 p-3 text-[12.5px] text-text-2"
								// install.php output is developer-authored markup (level-2 gated).
								dangerouslySetInnerHTML={{ __html: sanitizeHtml(result.output) }}
							/>
						</div>
					) : (
						<p className="text-[12.5px] text-text-3">
							The extension installed cleanly.
						</p>
					)}
					<div className="flex gap-2">
						<button
							type="button"
							onClick={() => navigate("/developer/extensions")}
							className="rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover"
						>
							Done
						</button>
						<button
							type="button"
							onClick={reset}
							className="rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
						>
							Install another
						</button>
					</div>
				</div>
			) : preview ? (
				<div className="space-y-4 rounded-xl border border-border bg-surface p-5">
					<div className="flex items-center gap-2">
						<Package size={16} className="text-text-3" />
						<span className="text-[14px] font-semibold text-text">
							{preview.manifest.title} {preview.manifest.version}
						</span>
						{preview.manifest.author?.name && (
							<span className="text-[12px] text-text-3">
								by {preview.manifest.author.name}
							</span>
						)}
					</div>

					{preview.errors.length > 0 && (
						<div className="rounded-md border border-danger/40 bg-danger/5 p-3">
							<div className="mb-1.5 flex items-center gap-1.5 text-[12px] font-semibold text-danger">
								<AlertTriangle size={13} />
								Errors — fix these before installing
							</div>
							<ul className="list-disc space-y-1 pl-5 text-[12px] text-danger">
								{preview.errors.map((e, i) => (
									<li key={i}>{e}</li>
								))}
							</ul>
						</div>
					)}

					{preview.warnings.length > 0 && (
						<div className="rounded-md border border-warn/40 bg-warn/5 p-3">
							<div className="mb-1.5 text-[12px] font-semibold text-warn">
								Warnings
							</div>
							<ul className="list-disc space-y-1 pl-5 text-[12px] text-text-2">
								{preview.warnings.map((w, i) => (
									<li key={i}>{w}</li>
								))}
							</ul>
						</div>
					)}

					{preview.ready && preview.warnings.length === 0 && (
						<p className="text-[12.5px] text-text-3">
							Ready to install — no problems found.
						</p>
					)}

					<div className="flex gap-2">
						<button
							type="button"
							disabled={!preview.ready || processMutation.isPending}
							onClick={() => processMutation.mutate()}
							className="rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover disabled:opacity-50"
						>
							{processMutation.isPending ? "Installing…" : "Install"}
						</button>
						<button
							type="button"
							onClick={reset}
							className="rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
						>
							Choose a different file
						</button>
					</div>
				</div>
			) : (
				<div className="space-y-4 rounded-xl border border-border bg-surface p-5">
					<input
						ref={inputRef}
						type="file"
						accept=".zip,application/zip"
						className="hidden"
						onChange={(e) => {
							setFile(e.target.files?.[0] ?? null);
							e.target.value = "";
						}}
					/>

					<div className="flex flex-wrap items-center gap-3">
						<button
							type="button"
							onClick={() => inputRef.current?.click()}
							className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
						>
							<Upload size={13} />
							Choose package
						</button>
						{file && (
							<span className="font-mono text-[12px] text-text-2">{file.name}</span>
						)}
					</div>

					<button
						type="button"
						disabled={!file || unpackMutation.isPending}
						onClick={() => file && unpackMutation.mutate(file)}
						className="rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover disabled:opacity-50"
					>
						{unpackMutation.isPending ? "Uploading…" : "Upload & review"}
					</button>
				</div>
			)}
		</div>
	);
};
