import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { AlertTriangle, CheckCircle2, ChevronLeft, Package, Upload } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { PageContainer } from "@/components/shell/PageContainer";
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
import { useFilePicker } from "@/hooks/useFilePicker";
import { describeApiError } from "@/lib/errorHandling";
import { toast } from "@/lib/toast";
import { sanitizeHtml } from "@/lib/html";
import { queryKeys } from "@/lib/queryKeys";

/**
 * Two-step extension installer (legacy install/{unpack,process}.php):
 *   1. Upload a .zip → server stages it and reports what installing would do.
 *   2. Review warnings/errors → commit, which runs the install SQL + install.php.
 */
export const ExtensionInstall = () => {
	const navigate = useNavigate();
	const queryClient = useQueryClient();

	const [file, setFile] = useState<File | null>(null);
	const [preview, setPreview] = useState<ExtensionInstallPreview | null>(null);
	const [result, setResult] = useState<ExtensionInstallResult | null>(null);
	const [error, setError] = useState<string | null>(null);
	const filePicker = useFilePicker((picked) => setFile(picked));

	const unpackMutation = useMutation({
		mutationFn: (f: File) => extensionsApi.installUnpack(f),
		onSuccess: (p) => {
			setPreview(p);
			setError(null);
		},
		onError: (err) => {
			setPreview(null);
			setError(describeApiError(err, "Could not read the package"));
		},
	});

	const processMutation = useMutation({
		mutationFn: () => extensionsApi.installProcess(),
		onSuccess: (r) => {
			setResult(r);
			queryClient.invalidateQueries({ queryKey: queryKeys.extensions.root() });
			toast.success("Extension installed");
		},
		onError: (err) => {
			setError(describeApiError(err, "Install failed"));
		},
	});

	const reset = () => {
		setFile(null);
		setPreview(null);
		setResult(null);
		setError(null);
	};

	return (
		<PageContainer width="narrow">
			<Breadcrumb
				items={[
					{ label: "Developer", to: "/developer" },
					{ label: "Extensions", to: "/developer/extensions" },
					{ label: "Install" },
				]}
			/>

			<PageHead
				actions={
					<Button icon={<ChevronLeft size={13} />} to="/developer/extensions">
						Back
					</Button>
				}
				sub="Upload an extension package (.zip). You'll review what it changes before it's installed."
				title="Install extension"
			/>

			<DeveloperSectionNav />

			{error && (
				<Alert className="mb-3" tone="danger">
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
							<SectionLabel as="h3" className="mb-1.5" size="sm">
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
						<Package className="text-text-3" size={16} />
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
							icon={<AlertTriangle size={13} />}
							title="Errors — fix these before installing"
							tone="danger"
						>
							<ul className="list-disc space-y-1 pl-5 text-[12px]">
								{preview.errors.map((e) => (
									<li key={e}>{e}</li>
								))}
							</ul>
						</Alert>
					)}

					{preview.warnings.length > 0 && (
						<Alert title="Warnings" tone="warn">
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
							disabled={!preview.ready}
							loading={processMutation.isPending}
							loadingLabel="Installing…"
							variant="primary"
							onClick={() => processMutation.mutate()}
						>
							Install
						</Button>
						<Button variant="secondary" onClick={reset}>
							Choose a different file
						</Button>
					</div>
				</Card>
			) : (
				<Card className="space-y-4 p-5">
					<input
						accept=".zip,application/zip"
						aria-label="Extension package file"
						className="hidden"
						ref={filePicker.inputRef}
						type="file"
						onChange={filePicker.onChange}
					/>

					<div className="flex flex-wrap items-center gap-3">
						<Button
							icon={<Upload size={13} />}
							variant="secondary"
							onClick={filePicker.open}
						>
							Choose package
						</Button>
						{file && (
							<span className="font-mono text-[12px] text-text-2">{file.name}</span>
						)}
					</div>

					<Button
						disabled={!file}
						loading={unpackMutation.isPending}
						loadingLabel="Uploading…"
						variant="primary"
						onClick={() => file && unpackMutation.mutate(file)}
					>
						Upload & review
					</Button>
				</Card>
			)}
		</PageContainer>
	);
};
