/**
 * Shared factory for field- and action-module loaders. Owns the asset-URL
 * cache (with rejected-load eviction), the contract-version guard, and the
 * blob-URL import/revoke dance used by local-source preview.
 *
 * Field and action contracts genuinely differ (imperative-DOM host vs React
 * component); only the load plumbing is shared here.
 */

export interface ModuleLoaderOptions<T> {
	/** Optional transform before blob-import (e.g. Sucrase for action TSX). */
	compile?: (source: string) => Promise<string>;
	hostContractVersion: number;
	isModule: (value: unknown) => value is T;
	/** Human noun for error messages, e.g. "Field" / "Action". */
	typeName: string;
}

export interface ModuleLoader<T> {
	loadFromSource: (source: string) => Promise<T>;
	loadFromUrl: (assetUrl: string) => Promise<T>;
}

export const createModuleLoader = <T extends { contractVersion: number }>(
	options: ModuleLoaderOptions<T>
): ModuleLoader<T> => {
	const { hostContractVersion, isModule, typeName, compile } = options;
	const cache = new Map<string, Promise<T>>();

	const validateModule = (mod: { default?: unknown }): T => {
		const def = mod.default;

		if (!isModule(def)) {
			throw new Error(
				`${typeName} module did not default-export a valid ${typeName.toLowerCase()} contract.`
			);
		}

		if (Math.floor(def.contractVersion) > hostContractVersion) {
			throw new Error(
				`${typeName} module needs contract v${def.contractVersion}; host supports v${hostContractVersion}.`
			);
		}

		return def;
	};

	const loadFromUrl = (assetUrl: string): Promise<T> => {
		let pending = cache.get(assetUrl);

		if (!pending) {
			pending = import(/* @vite-ignore */ assetUrl).then(validateModule);
			pending.catch(() => cache.delete(assetUrl));
			cache.set(assetUrl, pending);
		}

		return pending;
	};

	const loadFromSource = async (source: string): Promise<T> => {
		const body = compile ? await compile(source) : source;
		const url = URL.createObjectURL(new Blob([body], { type: "text/javascript" }));

		try {
			return validateModule(await import(/* @vite-ignore */ url));
		} finally {
			URL.revokeObjectURL(url);
		}
	};

	return { loadFromUrl, loadFromSource };
};
