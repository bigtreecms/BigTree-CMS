import { useState } from "react";
import { Database, RefreshCw, Save, Sparkles } from "lucide-react";

import { ConfigureLayout } from "@/components/developer/ConfigureLayout";
import { Button } from "@/components/ui/Button";
import { TextInput } from "@/components/ui/TextInput";
import { Field } from "@/components/ui/Field";
import { SelectField } from "@/components/ui/SelectField";
import { FormShell } from "@/components/ui/FormShell";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { Checkbox } from "@/components/ui/Checkbox";
import { Alert } from "@/components/ui/Alert";

import { configureApi, type AiConfig, type AiServiceId } from "@/api/endpoints/configure";
import { authApi } from "@/auth/endpoints";
import { useAuthStore } from "@/auth/store";

import { queryKeys } from "@/lib/queryKeys";
import { useConfigDraft } from "@/hooks/useConfigDraft";
import { useToastMutation } from "@/hooks/useToastMutation";
import { describeApiError } from "@/lib/errorHandling";

interface AiDraft {
	api_key: string;
	api_key_clear: boolean;
	api_key_set: boolean;
	embedding_api_key: string;
	embedding_api_key_clear: boolean;
	embedding_api_key_set: boolean;
	embedding_dimensions: number;
	embedding_key_required: boolean;
	embedding_model: string;
	embedding_models: AiConfig["embedding_models"];
	embeddings_ready: boolean;
	embeddings_supported: boolean;
	features: { search: boolean; chat: boolean; embeddings: boolean };
	final_max_tokens: string;
	final_max_tokens_default: number;
	max_tokens: string;
	max_tokens_default: number;
	model: string;
	models: AiConfig["models"];
	service: AiServiceId;
	token_max: number;
	token_min: number;
}

const SERVICES: Array<{ id: AiServiceId; label: string; help: string }> = [
	{
		id: "",
		label: "Disabled",
		help: "AI features stay off until a provider and API key are saved.",
	},
	{
		id: "xai",
		label: "xAI",
		help: "Grok models via the xAI API (OpenAI-compatible chat completions). Vector embeddings need a separate OpenAI key.",
	},
	{
		id: "openai",
		label: "OpenAI",
		help: "GPT models via the OpenAI Chat Completions API. The same key is used for embeddings unless you set a dedicated embeddings key.",
	},
	{
		id: "anthropic",
		label: "Anthropic",
		help: "Claude models via the Anthropic Messages API. Vector embeddings need a separate OpenAI key.",
	},
];

interface RemoveKeyButtonProps {
	onClick: () => void;
}

const RemoveKeyButton = ({ onClick }: RemoveKeyButtonProps) => (
	<button
		className="mt-1.5 text-[11px] text-text-3 underline underline-offset-2 hover:text-danger"
		type="button"
		onClick={onClick}
	>
		Remove stored key
	</button>
);

interface KeyClearNoticeProps {
	onUndo: () => void;
}

const KeyClearNotice = ({ onUndo }: KeyClearNoticeProps) => (
	<p className="mt-1.5 text-[11px] text-warn">
		Stored key will be removed when you save.{" "}
		<button
			className="underline underline-offset-2 hover:text-text"
			type="button"
			onClick={onUndo}
		>
			Keep it
		</button>
	</p>
);

export const ConfigureAI = () => {
	const setSession = useAuthStore((s) => s.setSession);
	const [reindexStatus, setReindexStatus] = useState<string | null>(null);

	const { detailQ, draft, setDraft, generalError, saveMutation, writeCache } = useConfigDraft({
		queryKey: queryKeys.configure.ai(),
		queryFn: () => configureApi.ai.get(),
		seed: (data: AiConfig): AiDraft => ({
			service: data.service,
			api_key: "",
			embedding_api_key: "",
			model: data.model,
			embedding_model: data.embedding_model ?? "",
			features: {
				search: !!data.features?.search,
				chat: !!data.features?.chat,
				embeddings: !!data.features?.embeddings,
			},
			api_key_set: !!data.api_key_set,
			embedding_api_key_set: !!data.embedding_api_key_set,
			api_key_clear: false,
			embedding_api_key_clear: false,
			models: data.models ?? {},
			embedding_models: data.embedding_models ?? {},
			embeddings_supported: !!data.embeddings_supported,
			embeddings_ready: !!data.embeddings_ready,
			embedding_dimensions: data.embedding_dimensions ?? 1536,
			embedding_key_required: !!data.embedding_key_required,
			// Held as strings so "" reads as "follow the provider default" in the
			// field itself, the way the server stores 0.
			max_tokens: data.max_tokens ? String(data.max_tokens) : "",
			final_max_tokens: data.final_max_tokens ? String(data.final_max_tokens) : "",
			max_tokens_default: data.max_tokens_default ?? 4096,
			final_max_tokens_default: data.final_max_tokens_default ?? 2048,
			token_min: data.token_min ?? 256,
			token_max: data.token_max ?? 64000,
		}),
		save: async (next: AiDraft) => {
			const fresh = await configureApi.ai.update({
				service: next.service,
				api_key: next.api_key,
				api_key_clear: next.api_key_clear,
				embedding_api_key: next.embedding_api_key,
				embedding_api_key_clear: next.embedding_api_key_clear,
				model: next.model,
				embedding_model: next.embedding_model,
				max_tokens: next.max_tokens.trim() === "" ? 0 : Number(next.max_tokens),
				final_max_tokens:
					next.final_max_tokens.trim() === "" ? 0 : Number(next.final_max_tokens),
				features: next.features,
			});

			// Refresh auth user features so ⌘K picks up AI search without re-login.
			try {
				const me = await authApi.me();
				const auth = useAuthStore.getState();

				if (auth.accessToken && auth.refreshToken) {
					const remainingSeconds = auth.expiresAt
						? Math.max(1, Math.floor((auth.expiresAt - Date.now()) / 1000))
						: 900;
					setSession(auth.accessToken, auth.refreshToken, remainingSeconds, me);
				}
			} catch {
				// Non-fatal — features update on next login/refresh.
			}

			return fresh;
		},
		successMessage: "AI service updated",
		errorMessage: "Could not save AI config",
	});

	const reindexMutation = useToastMutation({
		mutationFn: async () => {
			setReindexStatus("Starting rebuild…");
			// Probe page count
			const probe = await configureApi.ai.reindexEmbeddings(0);

			if (probe.complete || probe.pages < 1) {
				setReindexStatus(probe.response);

				return probe;
			}

			let last = probe;

			for (let page = 1; page <= probe.pages; page++) {
				setReindexStatus(`Indexing batch ${page} / ${probe.pages}…`);
				last = await configureApi.ai.reindexEmbeddings(page);

				if (last.complete) {
					break;
				}
			}

			setReindexStatus(last.response);

			return last;
		},
		successMessage: "Embeddings index rebuild finished",
		errorMessage: "Embeddings rebuild failed",
		onError: (err) => {
			setReindexStatus(describeApiError(err, "Rebuild failed"));
		},
		onSuccess: async () => {
			const fresh = await configureApi.ai.get();
			writeCache(fresh);
		},
	});

	const onSubmit = (e: React.FormEvent) => {
		e.preventDefault();

		if (!draft) {
			return;
		}

		saveMutation.mutate(draft);
	};

	const active = SERVICES.find((s) => s.id === draft?.service) ?? SERVICES[0]!;
	const modelOptions = draft?.service
		? (draft.models[draft.service] ?? []).map((m) => ({
				value: m.id,
				label: m.label,
			}))
		: [];
	const embeddingOptions = draft?.service
		? (draft.embedding_models[draft.service] ?? []).map((m) => ({
				value: m.id,
				label: m.label,
			}))
		: [];

	// A stored key only counts if it isn't queued for removal on save.
	const chatKeyPresent = !!(
		draft &&
		(draft.api_key.trim() !== "" || (draft.api_key_set && !draft.api_key_clear))
	);
	const dedicatedEmbedKeyPresent = !!(
		draft &&
		(draft.embedding_api_key.trim() !== "" ||
			(draft.embedding_api_key_set && !draft.embedding_api_key_clear))
	);
	const hasChatKey = chatKeyPresent;
	const hasEmbedKey = !!(
		draft &&
		(draft.service === "openai"
			? chatKeyPresent || dedicatedEmbedKeyPresent
			: dedicatedEmbedKeyPresent)
	);
	const canEnableSearch = !!(draft && draft.service && hasChatKey && draft.model);
	const canEnableEmbeddings = !!(
		draft &&
		draft.embeddings_supported &&
		draft.embeddings_ready &&
		draft.service &&
		hasEmbedKey &&
		embeddingOptions.length > 0 &&
		draft.embedding_model
	);

	return (
		<ConfigureLayout
			query={detailQ}
			sub="Provider credentials that power AI features across BigTree — conversational search and optional vector embeddings."
			title="AI"
		>
			{draft && (
				<FormShell
					footer={
						<Button
							icon={<Save size={13} />}
							loading={saveMutation.isPending}
							loadingLabel="Saving…"
							type="submit"
							variant="primary"
						>
							Save
						</Button>
					}
					onSubmit={onSubmit}
				>
					{generalError && <ErrorPanel message={generalError} />}

					<SelectField
						hint={active.help}
						label="Service"
						options={SERVICES.map((s) => ({ value: s.id, label: s.label }))}
						value={draft.service}
						onChange={(v) => {
							const service = v as AiServiceId;
							const models = draft.models[service] ?? [];
							const embModels = draft.embedding_models[service] ?? [];
							const nextModel =
								service === ""
									? ""
									: models.some((m) => m.id === draft.model)
										? draft.model
										: (models[0]?.id ?? "");
							const nextEmbed =
								service === ""
									? ""
									: embModels.some((m) => m.id === draft.embedding_model)
										? draft.embedding_model
										: (embModels[0]?.id ?? "");

							setDraft({
								...draft,
								service,
								model: nextModel,
								embedding_model: nextEmbed,
								embedding_key_required: service !== "" && service !== "openai",
								features: {
									search: service === "" ? false : draft.features.search,
									chat: service === "" ? false : draft.features.chat,
									embeddings:
										service === "" || embModels.length === 0
											? false
											: draft.features.embeddings,
								},
							});
						}}
					/>

					{draft.service !== "" && (
						<div className="mt-4 space-y-3">
							<Field
								hint={
									draft.api_key_set
										? "A key is stored. Leave blank to keep it, or paste a new key to replace it."
										: "Provider secret used for chat / tool-calling. Stored encrypted."
								}
								label="Chat API key"
							>
								<TextInput
									autoComplete="off"
									placeholder={
										draft.api_key_clear
											? "Stored key will be removed on save"
											: draft.api_key_set
												? "•••••••• (stored)"
												: "sk-…"
									}
									type="password"
									value={draft.api_key}
									onChange={(e) =>
										setDraft({
											...draft,
											api_key: e.target.value,
											api_key_clear: false,
										})
									}
								/>
								{draft.api_key_set &&
									(draft.api_key_clear ? (
										<KeyClearNotice
											onUndo={() =>
												setDraft({ ...draft, api_key_clear: false })
											}
										/>
									) : (
										<RemoveKeyButton
											onClick={() =>
												setDraft({
													...draft,
													api_key: "",
													api_key_clear: true,
												})
											}
										/>
									))}
							</Field>

							<SelectField
								hint="Used for conversational AI search and tool calling."
								label="Chat model"
								options={modelOptions}
								value={draft.model}
								onChange={(v) => setDraft({ ...draft, model: v })}
							/>

							<div className="grid gap-3 sm:grid-cols-2">
								<Field
									hint={`Tokens one tool-calling round may generate. Leave blank for this provider's default (${draft.max_tokens_default}). Raise it if the assistant writes long page content — a round cut off mid-tool-call is refused, not applied.`}
									label="Tokens per round"
								>
									<TextInput
										max={draft.token_max}
										min={draft.token_min}
										placeholder={`${draft.max_tokens_default} (default)`}
										type="number"
										value={draft.max_tokens}
										onChange={(e) =>
											setDraft({ ...draft, max_tokens: e.target.value })
										}
									/>
								</Field>

								<Field
									hint={`Tokens the final written answer may generate. Leave blank for this provider's default (${draft.final_max_tokens_default}). An answer that hits the limit is delivered with a note saying it was cut off.`}
									label="Tokens per answer"
								>
									<TextInput
										max={draft.token_max}
										min={draft.token_min}
										placeholder={`${draft.final_max_tokens_default} (default)`}
										type="number"
										value={draft.final_max_tokens}
										onChange={(e) =>
											setDraft({
												...draft,
												final_max_tokens: e.target.value,
											})
										}
									/>
								</Field>
							</div>

							{embeddingOptions.length > 0 && (
								<>
									<SelectField
										hint={`Always OpenAI · fixed ${draft.embedding_dimensions}-dimension VECTOR index. Changing models requires a full rebuild.`}
										label="Embedding model"
										options={embeddingOptions}
										value={draft.embedding_model}
										onChange={(v) => setDraft({ ...draft, embedding_model: v })}
									/>

									<Field
										hint={
											draft.embedding_key_required
												? draft.embedding_api_key_set
													? "A dedicated OpenAI embeddings key is stored. Leave blank to keep it."
													: "xAI/Anthropic have no embedding models. Paste an OpenAI API key to power vector search."
												: draft.embedding_api_key_set
													? "A dedicated embeddings key is stored. Leave blank to keep it, or paste a new one to replace it."
													: "Leave blank to reuse the OpenAI chat key above for embeddings."
										}
										label={
											draft.embedding_key_required
												? "OpenAI embedding API key"
												: "OpenAI embedding API key (optional)"
										}
									>
										<TextInput
											autoComplete="off"
											placeholder={
												draft.embedding_api_key_clear
													? "Stored key will be removed on save"
													: draft.embedding_api_key_set
														? "•••••••• (stored)"
														: draft.embedding_key_required
															? "sk-… (OpenAI)"
															: "Optional — defaults to chat key"
											}
											type="password"
											value={draft.embedding_api_key}
											onChange={(e) =>
												setDraft({
													...draft,
													embedding_api_key: e.target.value,
													embedding_api_key_clear: false,
												})
											}
										/>
										{draft.embedding_api_key_set &&
											(draft.embedding_api_key_clear ? (
												<KeyClearNotice
													onUndo={() =>
														setDraft({
															...draft,
															embedding_api_key_clear: false,
														})
													}
												/>
											) : (
												<RemoveKeyButton
													onClick={() =>
														setDraft({
															...draft,
															embedding_api_key: "",
															embedding_api_key_clear: true,
														})
													}
												/>
											))}
									</Field>
								</>
							)}

							<div className="rounded-lg border border-border bg-surface-2/40 p-3">
								<div className="mb-2 flex items-center gap-2 text-[12.5px] font-semibold text-text">
									<Sparkles className="text-accent" size={14} />
									Features
								</div>

								<Checkbox
									checked={draft.features.search}
									disabled={!canEnableSearch}
									label="Enable AI search"
									onChange={(checked) =>
										setDraft({
											...draft,
											features: { ...draft.features, search: checked },
										})
									}
								/>

								<p className="mt-2 mb-3 text-[12px] text-text-3">
									When enabled, the admin ⌘K search becomes conversational — the
									model uses tools against pages, modules, tags, and users instead
									of a simple database match.
								</p>

								<Checkbox
									checked={draft.features.chat}
									disabled={!canEnableSearch}
									label="Enable AI assistant"
									onChange={(checked) =>
										setDraft({
											...draft,
											features: { ...draft.features, chat: checked },
										})
									}
								/>

								<p className="mt-2 mb-3 text-[12px] text-text-3">
									Adds a chat assistant to the admin bar. It answers questions and
									finds content using the same permission-aware tools as AI search
									— each user only sees what their access level allows.
								</p>

								<Checkbox
									checked={draft.features.embeddings}
									disabled={!canEnableEmbeddings}
									label="Enable vector embeddings"
									onChange={(checked) =>
										setDraft({
											...draft,
											features: {
												...draft.features,
												embeddings: checked,
											},
										})
									}
								/>

								<p className="mt-2 text-[12px] text-text-3">
									Semantic search over pages, settings, and module content using a
									native VECTOR index. Requires MySQL 9+ or MariaDB 11.7+ and an
									OpenAI embedding key.
								</p>

								{!draft.embeddings_supported && (
									<p className="mt-2 text-[12px] text-warn">
										This database does not support VECTOR types (need MySQL 9+
										or MariaDB 11.7+).
									</p>
								)}

								{draft.embeddings_supported && !draft.embeddings_ready && (
									<p className="mt-2 text-[12px] text-warn">
										Embeddings table isn&apos;t created yet — save this page or
										press Rebuild index to create it.
									</p>
								)}

								{draft.embeddings_supported &&
									draft.embeddings_ready &&
									draft.embedding_key_required &&
									!hasEmbedKey && (
										<p className="mt-2 text-[12px] text-warn">
											Add an OpenAI embedding API key above to fill{" "}
											<code className="text-[11px]">
												bigtree_ai_embeddings
											</code>
											. xAI has no public embedding models.
										</p>
									)}

								{!canEnableSearch && (
									<p className="mt-2 text-[12px] text-warn">
										Choose a service, save an API key, and pick a model before
										enabling AI search.
									</p>
								)}
							</div>

							{draft.features.embeddings && canEnableEmbeddings && (
								<div className="rounded-lg border border-border p-3">
									<div className="mb-2 flex items-center gap-2 text-[12.5px] font-semibold text-text">
										<Database className="text-accent" size={14} />
										Embeddings index
									</div>
									<p className="mb-3 text-[12px] text-text-3">
										Rebuild the vector index after enabling embeddings or
										changing the embedding model. Runs in small batches to avoid
										timeouts.
									</p>
									<Button
										icon={<RefreshCw size={13} />}
										loading={reindexMutation.isPending}
										loadingLabel="Rebuilding…"
										type="button"
										variant="secondary"
										onClick={() => reindexMutation.mutate()}
									>
										Rebuild index
									</Button>
									{reindexStatus && (
										<p className="mt-2 text-[12px] text-text-2">
											{reindexStatus}
										</p>
									)}
								</div>
							)}

							<Alert className="mt-1" tone="info">
								AI search calls your chat provider; embeddings always call OpenAI
								and may incur usage charges. Keys never leave this site except to
								the selected provider&apos;s API.
							</Alert>
						</div>
					)}
				</FormShell>
			)}
		</ConfigureLayout>
	);
};
