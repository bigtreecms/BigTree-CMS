import { defineConfig, loadEnv } from "vite";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";
import path from "node:path";
import { URL } from "node:url";

/**
 * Vite config for the BigTree admin SPA.
 *
 * Dev: Vite runs at http://localhost:5173. The SPA's fetch wrapper hits paths
 * like `/admin/api/v1/auth/login` — relative URLs that resolve to the Vite
 * origin. This proxy intercepts that prefix and forwards to your real BigTree
 * install. The proxying happens server-side; in DevTools you'll always see
 * the URL as `http://localhost:5173/...` (that's the request the browser made).
 * What matters is whether the upstream forward succeeds.
 *
 * Configuration:
 *   VITE_API_TARGET should be the FULL URL of the API, including any path
 *   prefix your BigTree install lives under. The proxy strips `/admin/api/v1`
 *   from the SPA's path and replaces it with VITE_API_TARGET.
 *
 *   Examples:
 *     VITE_API_TARGET=http://localhost:8080/admin/api/v1
 *       → SPA's /admin/api/v1/auth/login → http://localhost:8080/admin/api/v1/auth/login
 *
 *     VITE_API_TARGET=http://bigtree.com/4.5/admin/api/v1
 *       → SPA's /admin/api/v1/auth/login → http://bigtree.com/4.5/admin/api/v1/auth/login
 *
 *     VITE_API_TARGET=http://bigtree.com/4.5/admin/api/v1/
 *       (trailing slash is tolerated and stripped)
 *
 * Prod: `npm run build` outputs to spa/dist/ — Apache serves it at /admin/spa/.
 * Since the SPA shares an origin with the PHP server, no proxy is involved at
 * runtime; the fetch wrapper's relative `/admin/api/v1/...` URLs just work.
 */
const SPA_API_PREFIX = "/admin/api/v1";

export default defineConfig(({ mode }) => {
	const env = loadEnv(mode, process.cwd(), "");
	const rawTarget = (env.VITE_API_TARGET || "http://localhost:8080/admin/api/v1").trim();

	// Parse + normalize the target into { origin, pathPrefix } so we can:
	//   1. Hand `origin` to http-proxy-middleware (target must be an origin)
	//   2. Rewrite the path: strip the SPA's /admin/api/v1 + prepend the target's path
	let origin: string;
	let pathPrefix: string;
	try {
		const url = new URL(rawTarget);
		origin = url.origin;
		// Strip trailing slash from the path so concatenation is clean
		pathPrefix = url.pathname.replace(/\/+$/, "");
	} catch {
		throw new Error(
			`VITE_API_TARGET is not a valid URL: ${rawTarget}\n` +
				`Expected something like http://localhost:8080/admin/api/v1`
		);
	}

	// One-line diagnostic so users can see at dev-server boot exactly what's
	// being proxied — saves a lot of "is my .env even loaded?" confusion.
	if (mode !== "production") {
		console.log(
			`[vite] Proxying ${SPA_API_PREFIX}/* → ${origin}${pathPrefix}/*  (set via VITE_API_TARGET)`
		);
	}

	return {
		base: mode === "production" ? "/admin/spa/" : "/",
		plugins: [react(), tailwindcss()],
		resolve: {
			alias: {
				"@": path.resolve(__dirname, "src"),
			},
		},
		server: {
			port: 5173,
			strictPort: true,
			proxy: {
				[SPA_API_PREFIX]: {
					target: origin,
					changeOrigin: true,
					// Don't validate the upstream TLS cert. Dev BigTree installs commonly
					// use self-signed or locally-trusted certs (mkcert, Valet, MAMP Pro,
					// etc.) which http-proxy-middleware rejects with a 500 by default,
					// even though Postman/browsers accept them. This is dev-only — in
					// prod the SPA and PHP share an origin, no proxy involved.
					secure: false,
					// Strip the SPA's /admin/api/v1 prefix and replace with whatever
					// path the configured target carries. Works for installs at root
					// (pathPrefix = "/admin/api/v1") and subpath installs alike.
					rewrite: (incoming) => pathPrefix + incoming.slice(SPA_API_PREFIX.length),
					// No cookie plumbing here. Refresh tokens live in localStorage
					// and travel in the request body — there's nothing for the proxy
					// to rewrite. Login + remember-me + refresh-on-401 work the same
					// in dev and prod, regardless of install path.

					// Surface upstream connection failures in the dev console — without
					// this, a TLS/DNS/connection error just becomes an opaque 500 in
					// the browser with no clue where to look.
					configure: (proxy) => {
						proxy.on("error", (err, req) => {
							console.error(
								`[vite proxy] ${req.method} ${req.url} → upstream error:`,
								err.message
							);
						});
						proxy.on("proxyReq", (_proxyReq, req) => {
							console.log(`[vite proxy] → ${req.method} ${req.url}`);
						});
						proxy.on("proxyRes", (proxyRes, req) => {
							const status = proxyRes.statusCode;
							const label = status && status >= 400 ? "⚠" : "✓";

							console.log(`[vite proxy] ${label} ${status} ${req.method} ${req.url}`);
						});
					},
				},
			},
		},
		build: {
			outDir: "dist",
			emptyOutDir: true,
			sourcemap: true,
			rollupOptions: {
				output: {
					manualChunks: {
						"react-vendor": ["react", "react-dom", "react-router-dom"],
						"query-vendor": ["@tanstack/react-query", "@tanstack/react-table"],
						"ui-vendor": ["lucide-react", "react-hook-form", "zod"],
					},
				},
			},
		},
	};
});
