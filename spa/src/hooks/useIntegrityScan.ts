import { useCallback, useRef, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";

import {
	integrityApi,
	type IntegrityError,
	type IntegrityModule,
	type IntegritySession,
} from "@/api/endpoints/integrity";
import { modulesApi } from "@/api/endpoints/modules";

/** A single broken link/image, resolved with where it lives and how to fix it. */
export interface ScanFinding {
	key: string;
	location: "Page" | "Module";
	source: string;
	type: IntegrityError["type"];
	field: string;
	url: string;
	editTo: string;
}

export type ScanPhase = "idle" | "scanning" | "paused" | "done";

/** Map a module's internal id to its URL route (built from the module list). */
type RouteResolver = (moduleId: string | number) => string | undefined;

const moduleEditLink = (
	mod: IntegrityModule | undefined,
	itemId: number | string,
	routeFor: RouteResolver
): string => {
	if (!mod) {
		return "#";
	}

	const route = routeFor(mod.module_id);

	if (!route) {
		return "/modules";
	}

	// The module is reached by route; editing goes through its conventional
	// "edit" action route (the legacy admin's `/<route>/edit/<id>`).
	if (mod.edit_view_id !== null && mod.edit_view_id !== undefined) {
		return `/modules/${route}/edit/${itemId}`;
	}

	return `/modules/${route}`;
};

const totalUnits = (session: IntegritySession): number =>
	session.pages.length + session.modules.reduce((sum, m) => sum + m.items.length, 0);

const toPageFinding = (
	id: string | number,
	navTitle: string,
	error: IntegrityError,
	index: number
): ScanFinding => ({
	key: `p-${id}-${error.field}-${error.url}-${index}`,
	location: "Page",
	source: navTitle || `Page ${id}`,
	type: error.type,
	field: error.field,
	url: error.url,
	editTo: `/pages/${id}/edit`,
});

const toModuleFinding = (
	mod: IntegrityModule | undefined,
	itemId: string | number,
	error: IntegrityError,
	index: number,
	routeFor: RouteResolver
): ScanFinding => ({
	key: `m-${mod?.id ?? "?"}-${itemId}-${error.field}-${error.url}-${index}`,
	location: "Module",
	source: mod?.name ?? "Module",
	type: error.type,
	field: error.field,
	url: error.url,
	editTo: moduleEditLink(mod, itemId, routeFor),
});

/**
 * Drives an incremental Site Integrity scan client-side: builds (or resumes) a
 * work list via the API, then checks one page or module entry at a time so the
 * UI can stream progress and findings. Mirrors the legacy dashboard scanner,
 * including resume-from-session and skip-and-continue on a failed item.
 *
 * `start(external)` kicks off (or resumes) a run; `stop()` pauses it after the
 * in-flight request settles; `clear()` resets local state to idle (call it after
 * an API reset). A monotonic token guards against a stale loop continuing to
 * write state after a newer run started.
 */
export const useIntegrityScan = () => {
	const queryClient = useQueryClient();
	const [phase, setPhase] = useState<ScanPhase>("idle");
	const [findings, setFindings] = useState<ScanFinding[]>([]);
	const [completed, setCompleted] = useState(0);
	const [total, setTotal] = useState(0);
	const [currentLabel, setCurrentLabel] = useState("");
	const [external, setExternal] = useState(false);

	const cancelled = useRef(false);
	const token = useRef(0);

	const start = useCallback(
		async (useExternal: boolean) => {
			token.current += 1;
			const myToken = token.current;
			cancelled.current = false;

			setExternal(useExternal);
			setPhase("scanning");
			setCurrentLabel("Preparing scan…");

			const session = await integrityApi.start(useExternal);

			if (cancelled.current || myToken !== token.current) {
				return;
			}

			setTotal(totalUnits(session));

			// Module findings link to the module by its route, so resolve module
			// id → route from the (cached) module list once up front.
			const modules = await queryClient.fetchQuery({
				queryKey: ["modules", "list"],
				queryFn: () => modulesApi.list(),
			});
			const routeById = new Map(modules.map((m) => [String(m.id), m.route]));
			const routeFor: RouteResolver = (id) => routeById.get(String(id));

			// Seed any errors already discovered in a resumed session.
			const moduleByForm = new Map(session.modules.map((m) => [m.id, m]));
			const seeded: ScanFinding[] = [];

			Object.entries(session.page_errors).forEach(([id, { nav_title, errors }]) => {
				errors.forEach((error, i) => seeded.push(toPageFinding(id, nav_title, error, i)));
			});

			Object.entries(session.module_errors).forEach(([formId, items]) => {
				Object.entries(items).forEach(([itemId, errors]) => {
					errors.forEach((error, i) =>
						seeded.push(
							toModuleFinding(moduleByForm.get(formId), itemId, error, i, routeFor)
						)
					);
				});
			});

			setFindings(seeded);

			// Work out where to resume. The legacy scanner finishes all pages before
			// touching modules, so any module progress means pages are already done.
			const resumingModules = session.current_module > 0 || session.current_item > 0;
			let done = 0;

			if (resumingModules) {
				done = session.pages.length;

				for (let m = 0; m < session.current_module; m++) {
					done += session.modules[m]?.items.length ?? 0;
				}

				done += session.current_item;
			} else {
				done = session.current_page;
			}

			setCompleted(done);

			// — Pages —
			if (!resumingModules) {
				for (let i = session.current_page; i < session.pages.length; i++) {
					if (cancelled.current || myToken !== token.current) {
						return;
					}

					const id = session.pages[i];

					if (id === undefined) {
						continue;
					}

					setCurrentLabel(`Scanning pages — ${i + 1} of ${session.pages.length}`);

					try {
						const res = await integrityApi.checkPage(useExternal, id, i);

						if (res.errors.length) {
							setFindings((prev) => [
								...prev,
								...res.errors.map((error, idx) =>
									toPageFinding(res.id, res.nav_title, error, idx)
								),
							]);
						}
					} catch {
						// Skip a page that errored/timed out and keep going, like the legacy scanner.
					}

					setCompleted((c) => c + 1);
				}
			}

			// — Module entries —
			const startModule = resumingModules ? session.current_module : 0;

			for (let m = startModule; m < session.modules.length; m++) {
				const mod = session.modules[m];

				if (!mod) {
					continue;
				}

				const startItem =
					resumingModules && m === session.current_module ? session.current_item : 0;

				for (let j = startItem; j < mod.items.length; j++) {
					if (cancelled.current || myToken !== token.current) {
						return;
					}

					const itemId = mod.items[j];

					if (itemId === undefined) {
						continue;
					}

					setCurrentLabel(`Scanning ${mod.name} — ${j + 1} of ${mod.items.length}`);

					try {
						const res = await integrityApi.checkModuleItem({
							external: useExternal,
							form: mod.id,
							id: itemId,
							module: m,
							index: j,
						});

						if (res.errors.length) {
							setFindings((prev) => [
								...prev,
								...res.errors.map((error, idx) =>
									toModuleFinding(mod, res.id, error, idx, routeFor)
								),
							]);
						}
					} catch {
						// Skip and continue.
					}

					setCompleted((c) => c + 1);
				}
			}

			if (cancelled.current || myToken !== token.current) {
				return;
			}

			setCurrentLabel("");
			setPhase("done");
		},
		[queryClient]
	);

	const stop = useCallback(() => {
		cancelled.current = true;
		setPhase("paused");
	}, []);

	const clear = useCallback(() => {
		token.current += 1;
		cancelled.current = true;
		setPhase("idle");
		setFindings([]);
		setCompleted(0);
		setTotal(0);
		setCurrentLabel("");
	}, []);

	return { phase, findings, completed, total, currentLabel, external, start, stop, clear };
};
