import { useCallback, useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { AlertTriangle, CheckCircle2, Database, LogOut } from "lucide-react";

import { useQueryClient } from "@tanstack/react-query";

import { authApi } from "@/auth/endpoints";
import { useAuthStore } from "@/auth/store";
import { systemApi } from "@/api/endpoints/system";
import { describeApiError } from "@/lib/errorHandling";
import { queryKeys } from "@/lib/queryKeys";
import { Button } from "@/components/ui/Button";
import { Card, CardHeader } from "@/components/ui/Card";
import { SectionLabel } from "@/components/ui/SectionLabel";
import { Loading } from "@/components/ui/Loading";
import { ErrorPanel } from "@/components/ui/ErrorPanel";
import { PageContainer } from "@/components/shell/PageContainer";
import { PageHead } from "@/components/shell/PageHead";

type Stage = "loading" | "ready" | "running" | "complete" | "error";

/**
 * Forced developer gate when the core ships new revisions/N.php files that have
 * not been applied yet. Mirrors the legacy admin "login → run upgrade scripts"
 * behavior without requiring a full remote core download.
 */
export const RunMigrations = () => {
	const navigate = useNavigate();
	const queryClient = useQueryClient();
	const setUser = useAuthStore((s) => s.setUser);
	const clear = useAuthStore((s) => s.clear);
	const [stage, setStage] = useState<Stage>("loading");
	const [queue, setQueue] = useState<string[]>([]);
	const [current, setCurrent] = useState<string | null>(null);
	const [log, setLog] = useState<string[]>([]);
	const [error, setError] = useState<string | null>(null);
	const [meta, setMeta] = useState<{ current: number | null; target: number | null }>({
		current: null,
		target: null,
	});
	const autoStarted = useRef(false);

	const appendLog = useCallback((line: string) => {
		setLog((prev) => (line && prev[prev.length - 1] !== line ? [...prev, line] : prev));
	}, []);

	const loadQueue = useCallback(async () => {
		setStage("loading");
		setError(null);

		try {
			const data = await systemApi.upgrade.migrations();
			setQueue(data.queue);
			setMeta({
				current: data.current_revision ?? null,
				target: data.target_revision ?? null,
			});

			if (!data.queue.length) {
				// Queue empty — re-fetch me so the gate flag clears, then leave.
				// Stay put if me still claims pending (mismatch) so we don't bounce.
				const me = await authApi.me();
				setUser(me);

				if (me.migrations_pending) {
					setError(
						"The server reported pending migrations but returned an empty queue. Check that core/version.php BIGTREE_REVISION matches deployed revision files, then reload."
					);
					setStage("error");

					return;
				}

				setStage("complete");
				navigate("/dashboard", { replace: true });

				return;
			}

			setStage("ready");
		} catch (err) {
			setError(describeApiError(err, "Could not load migration queue"));
			setStage("error");
		}
	}, [navigate, setUser]);

	useEffect(() => {
		void loadQueue();
	}, [loadQueue]);

	const runMigrations = useCallback(async () => {
		setStage("running");
		setError(null);
		setLog([]);

		try {
			/**
			 * Legacy revision protocol (see revisions/503.php):
			 *   1. Call with no page → may return { pages: N, complete: false }
			 *   2. Call page=1..N with total_pages=N until complete
			 * Cap at N so a bad script cannot loop forever.
			 *
			 * Re-fetch the queue before every script so a just-finished revision
			 * that advanced the floor does not leave us holding a stale list.
			 */
			const runScript = async (script: string) => {
				setCurrent(script);
				const first = await systemApi.upgrade.migrate(script);

				if (first.error) {
					throw new Error(first.error);
				}

				if (first.response) {
					appendLog(first.response);
				}

				if (first.complete) {
					return;
				}

				const totalPages = first.pages && first.pages > 0 ? first.pages : 0;

				if (totalPages < 1) {
					const safetyCap = 500;

					for (let page = 1; page <= safetyCap; page++) {
						setCurrent(`${script} (page ${page})`);
						const res = await systemApi.upgrade.migrate(script, page);

						if (res.error) {
							throw new Error(res.error);
						}

						if (res.response) {
							appendLog(res.response);
						}

						if (res.complete) {
							return;
						}
					}

					throw new Error(
						`${script} did not complete after ${safetyCap} pages (missing pages count)`
					);
				}

				for (let page = 1; page <= totalPages; page++) {
					setCurrent(`${script} (page ${page} / ${totalPages})`);
					const res = await systemApi.upgrade.migrate(script, page, totalPages);

					if (res.error) {
						throw new Error(res.error);
					}

					if (res.response) {
						appendLog(res.response);
					}

					if (res.complete) {
						return;
					}
				}
			};

			// Drain the live queue one head-script at a time (always re-query).
			const safetyScripts = 100;

			for (let i = 0; i < safetyScripts; i++) {
				const { queue: scripts } = await systemApi.upgrade.migrations();

				if (!scripts.length) {
					break;
				}

				const script = scripts[0]!;
				appendLog(`Running ${script}…`);
				setQueue(scripts);
				await runScript(script);
			}

			const finalQ = await systemApi.upgrade.migrations();

			if (finalQ.queue.length) {
				throw new Error(`Migrations remaining after run: ${finalQ.queue.join(", ")}`);
			}

			appendLog("All migrations complete.");
			await queryClient.invalidateQueries({ queryKey: queryKeys.system.migrations() });
			await queryClient.invalidateQueries({ queryKey: queryKeys.system.upgradeCheck() });
			const me = await authApi.me();
			setUser({ ...me, migrations_pending: false });
			setStage("complete");
			setCurrent(null);

			// Brief pause so the success state is visible, then enter the admin.
			window.setTimeout(() => {
				navigate("/dashboard", { replace: true });
			}, 800);
		} catch (err) {
			setError(describeApiError(err, "Migration failed"));
			setStage("error");
			setCurrent(null);
		}
	}, [appendLog, navigate, queryClient, setUser]);

	// Auto-start once the queue is known (one shot).
	useEffect(() => {
		if (stage === "ready" && queue.length > 0 && !autoStarted.current) {
			autoStarted.current = true;
			void runMigrations();
		}
	}, [stage, queue.length, runMigrations]);

	const logout = async () => {
		await authApi.logout();
		clear();
		navigate("/login", { replace: true });
	};

	return (
		<PageContainer width="narrow">
			<PageHead
				title="Database update required"
				sub="This BigTree install has pending schema migrations. Developers must apply them before using the admin."
			/>

			<Card className="overflow-hidden">
				<CardHeader>
					<div className="flex items-center gap-2">
						<Database size={15} className="text-accent" />
						<SectionLabel size="sm">Pending migrations</SectionLabel>
					</div>
				</CardHeader>

				<div className="space-y-4 p-4">
					{(meta.current != null || meta.target != null) && (
						<p className="text-[12.5px] text-text-2">
							Database revision{" "}
							<span className="font-mono tabular-nums text-text">
								{meta.current ?? "—"}
							</span>
							{" → "}
							<span className="font-mono tabular-nums text-text">
								{meta.target ?? "—"}
							</span>
							{queue.length > 0 && (
								<>
									{" "}
									· {queue.length} script{queue.length === 1 ? "" : "s"}
								</>
							)}
						</p>
					)}

					{stage === "loading" && <Loading label="Checking for pending migrations…" />}

					{stage === "ready" && (
						<div className="flex items-start gap-2 rounded-lg border border-warn/40 bg-warn-bg/30 p-3 text-[12.5px] text-text-2">
							<AlertTriangle size={15} className="mt-0.5 shrink-0 text-warn" />
							Starting migrations…
						</div>
					)}

					{stage === "running" && (
						<div className="space-y-3">
							<Loading
								label={
									current ? `Running ${current}…` : "Running database migrations…"
								}
							/>
							{queue.length > 0 && (
								<ul className="max-h-40 overflow-auto rounded-md border border-border bg-surface-2/40 px-3 py-2 font-mono text-[11.5px] text-text-3">
									{queue.map((script) => (
										<li key={script}>{script}</li>
									))}
								</ul>
							)}
						</div>
					)}

					{stage === "complete" && (
						<div className="flex items-center gap-2 text-[13px] font-medium text-accent">
							<CheckCircle2 size={16} />
							Database is up to date. Redirecting…
						</div>
					)}

					{error && <ErrorPanel message={error} />}

					{log.length > 0 && (
						<pre className="max-h-56 overflow-auto rounded-md border border-border bg-surface-2/40 p-3 text-[11.5px] leading-relaxed text-text-2">
							{log.join("\n")}
						</pre>
					)}

					<div className="flex flex-wrap items-center gap-2 pt-1">
						{(stage === "error" || stage === "ready") && (
							<Button
								variant="primary"
								onClick={() => {
									autoStarted.current = true;
									void runMigrations();
								}}
							>
								{stage === "error" ? "Retry migrations" : "Run migrations"}
							</Button>
						)}

						{stage === "error" && (
							<Button
								variant="secondary"
								onClick={() => {
									autoStarted.current = false;
									void loadQueue();
								}}
							>
								Reload queue
							</Button>
						)}

						<Button
							variant="secondary"
							icon={<LogOut size={13} />}
							onClick={() => void logout()}
						>
							Log out
						</Button>
					</div>
				</div>
			</Card>
		</PageContainer>
	);
};
