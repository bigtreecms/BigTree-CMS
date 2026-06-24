import { useRef, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { AlertTriangle, CheckCircle2, ChevronLeft, Package, Upload } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { SectionLabel } from "@/components/ui/SectionLabel";
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
		<div className="mx-auto max-w-3xl px-6 py-4">
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
					<Button icon={<ChevronLeft size={13} />} to="/developer/extensions">
						Back
					</Button>
				}
			/>

			<DeveloperSectionNav />

			{error && (
				<Alert tone="danger" className="mb-3">
					{error}
				</Alert>
			)}

			{result ? (
				<Card className="space-y-4 p-5">
					<div className="flex items-center gap-2 text-success">
						<CheckCircle2 size={18} />
						<span className="text-[14px] font-semibold">Installed “{result.id}”</span>
					</div>
					{result.output ? (
						<div>
							<SectionLabel as="h3" size="sm" className="mb-1.5">
								Installer output
							</SectionLabel>
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
						<Button variant="primary" onClick={() => navigate("/developer/extensions")}>
							Done
						</Button>
						<Button variant="secondary" onClick={reset}>
							Install another
						</Button>
					</div>
				</Card>
			) : preview ? (
				<Card className="space-y-4 p-5">
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
						<Alert
							tone="danger"
							icon={<AlertTriangle size={13} />}
							title="Errors — fix these before installing"
						>
							<ul className="list-disc space-y-1 pl-5 text-[12px]">
								{preview.errors.map((e) => (
									<li key={e}>{e}</li>
								))}
							</ul>
						</Alert>
					)}

					{preview.warnings.length > 0 && (
						<Alert tone="warn" title="Warnings">
							<ul className="list-disc space-y-1 pl-5 text-[12px] text-text-2">
								{preview.warnings.map((w) => (
									<li key={w}>{w}</li>
								))}
							</ul>
						</Alert>
					)}

					{preview.ready && preview.warnings.length === 0 && (
						<p className="text-[12.5px] text-text-3">
							Ready to install — no problems found.
						</p>
					)}

					<div className="flex gap-2">
						<Button
							variant="primary"
							disabled={!preview.ready || processMutation.isPending}
							onClick={() => processMutation.mutate()}
						>
							{processMutation.isPending ? "Installing…" : "Install"}
						</Button>
						<Button variant="secondary" onClick={reset}>
							Choose a different file
						</Button>
					</div>
				</Card>
			) : (
				<Card className="space-y-4 p-5">
					<input
						ref={inputRef}
						type="file"
						aria-label="Extension package file"
						accept=".zip,application/zip"
						className="hidden"
						onChange={(e) => {
							setFile(e.target.files?.[0] ?? null);
							e.target.value = "";
						}}
					/>

					<div className="flex flex-wrap items-center gap-3">
						<Button
							variant="secondary"
							icon={<Upload size={13} />}
							onClick={() => inputRef.current?.click()}
						>
							Choose package
						</Button>
						{file && (
							<span className="font-mono text-[12px] text-text-2">{file.name}</span>
						)}
					</div>

					<Button
						variant="primary"
						disabled={!file || unpackMutation.isPending}
						onClick={() => file && unpackMutation.mutate(file)}
					>
						{unpackMutation.isPending ? "Uploading…" : "Upload & review"}
					</Button>
				</Card>
			)}
		</div>
	);
};
