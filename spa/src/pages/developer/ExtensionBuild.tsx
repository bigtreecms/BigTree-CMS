import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import { ChevronLeft, Download, Package } from "lucide-react";

import { Breadcrumb } from "@/components/shell/Breadcrumb";
import { PageHead } from "@/components/shell/PageHead";
import { DeveloperSectionNav } from "@/components/developer/DeveloperSectionNav";

import {
	extensionsApi,
	type ExtensionBuildBody,
	type ExtensionBuildInspect,
	type ExtensionBuildResult,
} from "@/api/endpoints/extensions";
import { modulesApi } from "@/api/endpoints/modules";
import { templatesApi } from "@/api/endpoints/templates";
import { calloutsApi } from "@/api/endpoints/callouts";
import { feedsApi } from "@/api/endpoints/feeds";
import { settingsApi } from "@/api/endpoints/settings";
import { ApiError } from "@/types/api";
import { TextInput } from "@/components/developer/module-designer/inputs";

type Step = "details" | "components" | "files" | "review";

interface Picked {
	modules: Set<string>;
	templates: Set<string>;
	callouts: Set<string>;
	settings: Set<string>;
	feeds: Set<string>;
}

const STEPS: { id: Step; label: string }[] = [
	{ id: "details", label: "Details" },
	{ id: "components", label: "Components" },
	{ id: "files", label: "Files & tables" },
	{ id: "review", label: "Review" },
];

/**
 * Extension build/packaging wizard (legacy extensions/build/*). Collects details
 * and components, asks the server to infer the implied tables/files, lets the
 * developer trim them, then packages — which is destructive (namespaces ids and
 * moves files), so the final step is an explicit confirm.
 */
export const ExtensionBuild = () => {
	const queryClient = useQueryClient();
	const [step, setStep] = useState<Step>("details");

	// Details
	const [id, setId] = useState("");
	const [title, setTitle] = useState("");
	const [version, setVersion] = useState("");
	const [compatibility, setCompatibility] = useState("");
	const [description, setDescription] = useState("");
	const [keywords, setKeywords] = useState("");
	const [authorName, setAuthorName] = useState("");
	const [authorEmail, setAuthorEmail] = useState("");
	const [authorUrl, setAuthorUrl] = useState("");
	const [openLicenses, setOpenLicenses] = useState<Set<string>>(new Set());
	const [closedLicense, setClosedLicense] = useState("");

	// Components
	const [picked, setPicked] = useState<Picked>({
		modules: new Set(),
		templates: new Set(),
		callouts: new Set(),
		settings: new Set(),
		feeds: new Set(),
	});

	// Inferred (after inspect), trimmable
	const [inspect, setInspect] = useState<ExtensionBuildInspect | null>(null);
	const [keptFiles, setKeptFiles] = useState<Set<string>>(new Set());
	const [keptTables, setKeptTables] = useState<Set<string>>(new Set());

	const [result, setResult] = useState<ExtensionBuildResult | null>(null);
	const [error, setError] = useState<string | null>(null);
	const [detailsError, setDetailsError] = useState<Record<string, string>>({});

	const licensesQ = useQuery({
		queryKey: ["extensions", "build", "licenses"],
		queryFn: () => extensionsApi.buildLicenses(),
	});
	const modulesQ = useQuery({ queryKey: ["modules", "list"], queryFn: () => modulesApi.list() });
	const templatesQ = useQuery({
		queryKey: ["templates", "list"],
		queryFn: () => templatesApi.list(),
	});
	const calloutsQ = useQuery({
		queryKey: ["callouts", "list"],
		queryFn: () => calloutsApi.list(),
	});
	const feedsQ = useQuery({ queryKey: ["feeds", "list"], queryFn: () => feedsApi.list() });
	const settingsQ = useQuery({
		queryKey: ["settings", "list"],
		queryFn: () => settingsApi.list(),
	});

	const inspectMutation = useMutation({
		mutationFn: () =>
			extensionsApi.buildInspect({
				id,
				modules: [...picked.modules],
				templates: [...picked.templates],
				callouts: [...picked.callouts],
				settings: [...picked.settings],
				feeds: [...picked.feeds],
			}),
		onSuccess: (data) => {
			setInspect(data);
			setKeptFiles(new Set(data.files));
			setKeptTables(new Set(data.tables));
			setStep("files");
			setError(null);
		},
		onError: (err) =>
			setError(err instanceof ApiError && err.message ? err.message : "Could not inspect"),
	});

	const buildMutation = useMutation({
		mutationFn: (body: ExtensionBuildBody) => extensionsApi.build(body),
		onSuccess: (r) => {
			setResult(r);
			queryClient.invalidateQueries({ queryKey: ["extensions"] });
		},
		onError: (err) =>
			setError(err instanceof ApiError && err.message ? err.message : "Build failed"),
	});

	const toggle = (key: keyof Picked, value: string) => {
		setPicked((prev) => {
			const next = new Set(prev[key]);

			if (next.has(value)) {
				next.delete(value);
			} else {
				next.add(value);
			}

			return { ...prev, [key]: next };
		});
	};

	const validateDetails = () => {
		const errs: Record<string, string> = {};

		if (!id.trim()) {
			errs.id = "An extension ID is required.";
		} else if (!/^[A-Za-z0-9._-]+$/.test(id.trim())) {
			errs.id = "Only letters, numbers, '.', '-', and '_'.";
		}

		if (!title.trim()) {
			errs.title = "A title is required.";
		}

		setDetailsError(errs);

		return Object.keys(errs).length === 0;
	};

	const goFromDetails = () => {
		if (validateDetails()) {
			setStep("components");
		}
	};

	const doBuild = () => {
		const body: ExtensionBuildBody = {
			id: id.trim(),
			title: title.trim(),
			version: version.trim() || undefined,
			compatibility: compatibility.trim() || undefined,
			description: description.trim() || undefined,
			keywords: keywords
				.split(",")
				.map((k) => k.trim())
				.filter(Boolean),
			author: { name: authorName, email: authorEmail, url: authorUrl },
			licenses: [...openLicenses],
			license: closedLicense || undefined,
			modules: [...picked.modules],
			templates: [...picked.templates],
			callouts: [...picked.callouts],
			settings: [...picked.settings],
			feeds: [...picked.feeds],
			field_types: inspect?.field_types ?? [],
			module_groups: inspect?.module_groups ?? [],
			tables: [...keptTables],
			files: [...keptFiles],
		};

		buildMutation.mutate(body);
	};

	const totalPicked = useMemo(
		() =>
			picked.modules.size +
			picked.templates.size +
			picked.callouts.size +
			picked.settings.size +
			picked.feeds.size,
		[picked]
	);

	return (
		<div className="mx-auto max-w-screen-md px-6 py-4">
			<Breadcrumb
				items={[
					{ label: "Developer", to: "/developer" },
					{ label: "Extensions", to: "/developer/extensions" },
					{ label: "Build" },
				]}
			/>

			<PageHead
				title="Build extension"
				sub="Package modules, templates, and other components into a distributable extension."
				actions={
					<Link
						to="/developer/extensions"
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
					>
						<ChevronLeft size={13} />
						Back
					</Link>
				}
			/>

			<DeveloperSectionNav />

			<ol className="mb-4 flex flex-wrap gap-2 text-[12px]">
				{STEPS.map((s, i) => (
					<li
						key={s.id}
						className={`rounded-full px-2.5 py-1 ${
							s.id === step ? "bg-accent text-accent-fg" : "bg-surface-2 text-text-3"
						}`}
					>
						{i + 1}. {s.label}
					</li>
				))}
			</ol>

			{error && (
				<div className="mb-3 rounded-md border border-danger/40 bg-danger/5 px-3 py-2 text-[12.5px] text-danger">
					{error}
				</div>
			)}

			{result ? (
				<div className="space-y-4 rounded-xl border border-border bg-surface p-5">
					<div className="flex items-center gap-2 text-text">
						<Package size={18} className="text-text-3" />
						<span className="text-[14px] font-semibold">
							Built “{result.id}” successfully
						</span>
					</div>
					<p className="text-[12.5px] text-text-3">
						The extension was packaged and registered. Download the zip to distribute
						it.
					</p>
					<div className="flex gap-2">
						<a
							href={result.download_url}
							className="inline-flex items-center gap-1.5 rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover"
						>
							<Download size={13} />
							Download package
						</a>
						<Link
							to="/developer/extensions"
							className="rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
						>
							Done
						</Link>
					</div>
				</div>
			) : (
				<div className="rounded-xl border border-border bg-surface p-5">
					{step === "details" && (
						<div className="space-y-4">
							<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
								<TextInput
									label="Extension ID"
									value={id}
									onChange={setId}
									error={detailsError.id}
									hint="e.g. com.fastspot.news"
									required
									mono
								/>
								<TextInput
									label="Title"
									value={title}
									onChange={setTitle}
									error={detailsError.title}
									required
								/>
								<TextInput label="Version" value={version} onChange={setVersion} />
								<TextInput
									label="BigTree compatibility"
									value={compatibility}
									onChange={setCompatibility}
									hint="e.g. 4.5+"
								/>
							</div>
							<label className="block">
								<span className="mb-1 block text-[12px] font-medium text-text-2">
									Description
								</span>
								<textarea
									value={description}
									onChange={(e) => setDescription(e.target.value)}
									rows={2}
									className="w-full rounded-md border border-border bg-surface px-3 py-2 text-[13px] focus:outline-none focus:ring-1 focus:ring-accent-ring"
								/>
							</label>
							<TextInput
								label="Keywords"
								value={keywords}
								onChange={setKeywords}
								hint="Separate with commas."
							/>
							<div className="grid grid-cols-1 gap-4 md:grid-cols-3">
								<TextInput
									label="Author name"
									value={authorName}
									onChange={setAuthorName}
								/>
								<TextInput
									label="Author email"
									value={authorEmail}
									onChange={setAuthorEmail}
								/>
								<TextInput
									label="Author URL"
									value={authorUrl}
									onChange={setAuthorUrl}
								/>
							</div>

							{licensesQ.data && (
								<div className="grid grid-cols-1 gap-4 md:grid-cols-2">
									<div>
										<span className="mb-1 block text-[12px] font-medium text-text-2">
											Open-source licenses
										</span>
										<div className="space-y-1">
											{Object.keys(licensesQ.data["Open Source"]).map(
												(name) => (
													<label
														key={name}
														className="flex items-center gap-2 text-[12.5px] text-text-2"
													>
														<input
															type="checkbox"
															className="h-4 w-4 accent-accent"
															checked={openLicenses.has(name)}
															onChange={() =>
																setOpenLicenses((prev) => {
																	const next = new Set(prev);

																	if (next.has(name)) {
																		next.delete(name);
																	} else {
																		next.add(name);
																	}

																	return next;
																})
															}
														/>
														{name}
													</label>
												)
											)}
										</div>
									</div>
									<div>
										<span className="mb-1 block text-[12px] font-medium text-text-2">
											Closed-source license
										</span>
										<div className="space-y-1">
											{Object.keys(licensesQ.data["Closed Source"]).map(
												(name) => (
													<label
														key={name}
														className="flex items-center gap-2 text-[12.5px] text-text-2"
													>
														<input
															type="radio"
															name="closed-license"
															className="h-4 w-4 accent-accent"
															checked={closedLicense === name}
															onChange={() => setClosedLicense(name)}
														/>
														{name}
													</label>
												)
											)}
										</div>
									</div>
								</div>
							)}

							<div className="flex justify-end">
								<button
									type="button"
									onClick={goFromDetails}
									className="rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover"
								>
									Next: components
								</button>
							</div>
						</div>
					)}

					{step === "components" && (
						<div className="space-y-5">
							<ComponentChecklist
								title="Modules"
								items={(modulesQ.data ?? []).map((m) => ({
									id: m.id,
									name: m.name,
								}))}
								picked={picked.modules}
								onToggle={(v) => toggle("modules", v)}
							/>
							<ComponentChecklist
								title="Templates"
								items={(templatesQ.data ?? []).map((t) => ({
									id: t.id,
									name: t.name,
								}))}
								picked={picked.templates}
								onToggle={(v) => toggle("templates", v)}
							/>
							<ComponentChecklist
								title="Callouts"
								items={(calloutsQ.data ?? []).map((c) => ({
									id: c.id,
									name: c.name,
								}))}
								picked={picked.callouts}
								onToggle={(v) => toggle("callouts", v)}
							/>
							<ComponentChecklist
								title="Settings"
								items={(settingsQ.data?.data ?? []).map((s) => ({
									id: s.id,
									name: s.name,
								}))}
								picked={picked.settings}
								onToggle={(v) => toggle("settings", v)}
							/>
							<ComponentChecklist
								title="Feeds"
								items={(feedsQ.data ?? []).map((f) => ({ id: f.id, name: f.name }))}
								picked={picked.feeds}
								onToggle={(v) => toggle("feeds", v)}
							/>

							<p className="text-[11.5px] text-text-3">
								Custom field types used by these components are detected and
								included automatically.
							</p>

							<div className="flex justify-between">
								<button
									type="button"
									onClick={() => setStep("details")}
									className="rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
								>
									Back
								</button>
								<button
									type="button"
									disabled={totalPicked === 0 || inspectMutation.isPending}
									onClick={() => inspectMutation.mutate()}
									className="rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover disabled:opacity-50"
								>
									{inspectMutation.isPending
										? "Inspecting…"
										: "Next: files & tables"}
								</button>
							</div>
						</div>
					)}

					{step === "files" && inspect && (
						<div className="space-y-5">
							<TrimList
								title="Tables"
								all={inspect.tables}
								kept={keptTables}
								setKept={setKeptTables}
								empty="No tables inferred."
							/>
							<TrimList
								title="Files"
								all={inspect.files}
								kept={keptFiles}
								setKept={setKeptFiles}
								empty="No files inferred."
								mono
							/>

							<div className="flex justify-between">
								<button
									type="button"
									onClick={() => setStep("components")}
									className="rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
								>
									Back
								</button>
								<button
									type="button"
									onClick={() => setStep("review")}
									className="rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover"
								>
									Next: review
								</button>
							</div>
						</div>
					)}

					{step === "review" && (
						<div className="space-y-4">
							<dl className="grid grid-cols-[140px_1fr] gap-y-1.5 text-[12.5px]">
								<dt className="text-text-3">ID</dt>
								<dd className="font-mono text-text-2">{id}</dd>
								<dt className="text-text-3">Title</dt>
								<dd className="text-text-2">{title}</dd>
								<dt className="text-text-3">Version</dt>
								<dd className="text-text-2">{version || "—"}</dd>
								<dt className="text-text-3">Components</dt>
								<dd className="text-text-2">{totalPicked} selected</dd>
								<dt className="text-text-3">Tables</dt>
								<dd className="text-text-2">{keptTables.size}</dd>
								<dt className="text-text-3">Files</dt>
								<dd className="text-text-2">{keptFiles.size}</dd>
							</dl>

							<div className="rounded-md border border-warn/40 bg-warn/5 px-3 py-2 text-[12px] text-text-2">
								Building namespaces the selected components into this extension
								(renaming their IDs) and moves their files into{" "}
								<span className="font-mono">extensions/{id}/</span>. This modifies
								your install.
							</div>

							<div className="flex justify-between">
								<button
									type="button"
									onClick={() => setStep("files")}
									className="rounded-md border border-border bg-surface px-3 py-1.5 text-[12.5px] hover:bg-hover"
								>
									Back
								</button>
								<button
									type="button"
									disabled={buildMutation.isPending}
									onClick={doBuild}
									className="rounded-md bg-accent px-3 py-1.5 text-[12.5px] font-medium text-accent-fg hover:bg-accent-hover disabled:opacity-50"
								>
									{buildMutation.isPending ? "Building…" : "Build extension"}
								</button>
							</div>
						</div>
					)}
				</div>
			)}
		</div>
	);
};

interface ChecklistItem {
	id: string;
	name: string;
}

const ComponentChecklist = ({
	title,
	items,
	picked,
	onToggle,
}: {
	title: string;
	items: ChecklistItem[];
	picked: Set<string>;
	onToggle: (id: string) => void;
}) => (
	<div>
		<span className="mb-1.5 block text-[12px] font-semibold text-text">{title}</span>
		{items.length === 0 ? (
			<p className="text-[11.5px] text-text-3">None available.</p>
		) : (
			<div className="grid grid-cols-1 gap-1 sm:grid-cols-2">
				{items.map((it) => (
					<label
						key={it.id}
						className="flex items-center gap-2 text-[12.5px] text-text-2"
					>
						<input
							type="checkbox"
							className="h-4 w-4 accent-accent"
							checked={picked.has(it.id)}
							onChange={() => onToggle(it.id)}
						/>
						<span className="truncate">{it.name}</span>
					</label>
				))}
			</div>
		)}
	</div>
);

const TrimList = ({
	title,
	all,
	kept,
	setKept,
	empty,
	mono,
}: {
	title: string;
	all: string[];
	kept: Set<string>;
	setKept: (next: Set<string>) => void;
	empty: string;
	mono?: boolean;
}) => (
	<div>
		<span className="mb-1.5 block text-[12px] font-semibold text-text">
			{title} ({kept.size})
		</span>
		{all.length === 0 ? (
			<p className="text-[11.5px] text-text-3">{empty}</p>
		) : (
			<ul className="max-h-48 space-y-1 overflow-auto rounded-md border border-border bg-surface-2 p-2">
				{all.map((item) => (
					<li key={item} className="flex items-center gap-2">
						<input
							type="checkbox"
							className="h-4 w-4 accent-accent"
							checked={kept.has(item)}
							onChange={() => {
								const next = new Set(kept);

								if (next.has(item)) {
									next.delete(item);
								} else {
									next.add(item);
								}

								setKept(next);
							}}
						/>
						<span
							className={`truncate text-[12px] text-text-2 ${mono ? "font-mono" : ""}`}
						>
							{item}
						</span>
					</li>
				))}
			</ul>
		)}
	</div>
);
