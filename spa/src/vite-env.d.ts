/// <reference types="vite/client" />

interface ImportMetaEnv {
	/** Full URL of the BigTree API, e.g. http://localhost:8080/admin/api/v1 (dev). */
	readonly VITE_API_TARGET?: string;
	/**
	 * Optional explicit base for stored assets ({wwwroot}/{staticroot} files),
	 * e.g. a CDN or a backend on a different host. When unset, dev derives it
	 * from VITE_API_TARGET and prod uses the page origin.
	 */
	readonly VITE_ASSET_BASE?: string;
}
