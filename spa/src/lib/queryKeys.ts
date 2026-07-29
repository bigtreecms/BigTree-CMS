export const queryKeys = {
	auth: {
		root: () => ["auth"] as const,
		loginPolicy: () => ["auth", "login-policy"] as const,
		passkeys: () => ["auth", "passkeys"] as const,
	},

	dashboard: {
		root: () => ["dashboard"] as const,
		summary: () => ["dashboard", "summary"] as const,
		analytics: () => ["dashboard", "analytics"] as const,
		contentAlerts: () => ["dashboard", "content-alerts"] as const,
		integrity: () => ["dashboard", "integrity", "state"] as const,
	},

	pages: {
		root: () => ["pages"] as const,
		/** Prefix key for invalidating all page list queries regardless of parent. */
		lists: () => ["pages", "list"] as const,
		list: (parent: number) => ["pages", "list", parent] as const,
		detail: (id: number, opts?: { lineage?: boolean; pending?: boolean }) =>
			opts ? (["pages", "detail", id, opts] as const) : (["pages", "detail", id] as const),
		revisions: (id: number) => ["pages", "revisions", id] as const,
		draft: (pcid: number) => ["pages", "draft", pcid] as const,
		accessLevels: (pageId: number) => ["pages", "access-levels", pageId] as const,
		search: (q: string) => ["pages", "search", q] as const,
		seoRating: (id: number | undefined) => ["pages", "seo-rating", id] as const,
	},

	tags: {
		root: () => ["tags"] as const,
		list: (page: number, q: string) => ["tags", "list", { page, q }] as const,
		detail: (id: number) => ["tags", "detail", id] as const,
		exists: (q: string) => ["tags", "exists", q] as const,
		search: (q: string) => ["tags", "search", q] as const,
	},

	users: {
		root: () => ["users"] as const,
		me: () => ["users", "me"] as const,
		/** Prefix key for invalidating all user list queries regardless of params. */
		lists: () => ["users", "list"] as const,
		list: (params: { page?: number; q: string }) => ["users", "list", params] as const,
		detail: (id: number) => ["users", "detail", id] as const,
	},

	userSelect: {
		search: (q: string) => ["user-select", q] as const,
		resolve: (value: unknown) => ["user-select", "resolve", value] as const,
	},

	messages: {
		root: () => ["messages"] as const,
		/** Prefix key for invalidating all message list queries regardless of params. */
		lists: () => ["messages", "list"] as const,
		list: (params: { folder?: string; page?: number; per_page?: number }) =>
			["messages", "list", params] as const,
		detail: (id: number) => ["messages", "detail", id] as const,
		unreadCount: () => ["messages", "unread-count"] as const,
	},

	settings: {
		root: () => ["settings"] as const,
		/** Prefix key for invalidating all settings list queries regardless of params. */
		lists: () => ["settings", "list"] as const,
		list: (params: { page: number; per_page: number; q: string; include_system?: boolean }) =>
			["settings", "list", params] as const,
		detail: (id: string, opts?: { includeEncrypted?: boolean }) =>
			opts
				? (["settings", "detail", id, opts] as const)
				: (["settings", "detail", id] as const),
	},

	templates: {
		root: () => ["templates"] as const,
		list: () => ["templates", "list"] as const,
		detail: (id: string) => ["templates", "detail", id] as const,
	},

	feeds: {
		root: () => ["feeds"] as const,
		list: () => ["feeds", "list"] as const,
		detail: (id: string) => ["feeds", "detail", id] as const,
	},

	callouts: {
		root: () => ["callouts"] as const,
		list: () => ["callouts", "list"] as const,
		detail: (id: string) => ["callouts", "detail", id] as const,
	},

	calloutGroups: {
		root: () => ["callout-groups"] as const,
		list: () => ["callout-groups", "list"] as const,
		detail: (id: string) => ["callout-groups", "detail", id] as const,
	},

	modules: {
		root: () => ["modules"] as const,
		list: () => ["modules", "list"] as const,
		detail: (id: string) => ["modules", "detail", id] as const,
		/** The install-static icon vocabulary served by GET /module-icons. */
		icons: () => ["modules", "icons"] as const,
		// Flat keys used by ModuleLayout, ModuleView, ModuleReport, ModuleEntryAdd/Edit
		actions: (moduleId: string) => ["modules", "actions", moduleId] as const,
		views: (moduleId: string) => ["modules", "views", moduleId] as const,
		reports: (moduleId: string) => ["modules", "reports", moduleId] as const,
		forms: (moduleId: string) => ["modules", "forms", moduleId] as const,
		// Nested keys used by ModuleDesigner tabs (moduleId first)
		moduleActions: (moduleId: string) => ["modules", moduleId, "actions"] as const,
		moduleActionSchema: (moduleId: string, actionId: string | number | null) =>
			["modules", moduleId, "actions", actionId, "schema"] as const,
		moduleViews: (moduleId: string) => ["modules", moduleId, "views"] as const,
		moduleForms: (moduleId: string) => ["modules", moduleId, "forms"] as const,
		moduleReports: (moduleId: string) => ["modules", moduleId, "reports"] as const,
		gbpCategories: (moduleId: string) => ["modules", moduleId, "gbp-categories"] as const,
		// Runtime keys used by the form/report renderers
		listOptions: (
			moduleId: string | undefined,
			formId: string | undefined,
			column: string | undefined
		) => ["list-options", moduleId, formId, column] as const,
		reportPrepare: (moduleId: string, reportId: string) =>
			["module-report-prepare", moduleId, reportId] as const,
	},

	embedForms: {
		root: () => ["embed-form"] as const,
		detail: (hash: string) => ["embed-form", hash] as const,
	},

	moduleGroups: {
		root: () => ["module-groups"] as const,
		list: () => ["module-groups", "list"] as const,
		detail: (id: number) => ["module-groups", "detail", id] as const,
	},

	moduleEntries: {
		root: (moduleId: string) => ["module-entries", moduleId] as const,
		/** Prefix key for a specific view — used for invalidation. */
		view: (moduleId: string, viewId: string | number) =>
			["module-entries", moduleId, viewId] as const,
		/** Full query key for a view with fetch params. */
		viewQuery: (moduleId: string, viewId: string | number, params: unknown) =>
			["module-entries", moduleId, viewId, params] as const,
		detail: (moduleId: string, entryId: string, formId: string) =>
			["module-entries", moduleId, "detail", entryId, formId] as const,
	},

	pendingChanges: {
		root: () => ["pending-changes"] as const,
		list: (params: { mine: boolean }) => ["pending-changes", "list", params] as const,
		detail: (id: number) => ["pending-changes", "detail", id] as const,
	},

	extensions: {
		root: () => ["extensions"] as const,
		updates: () => ["extensions", "updates"] as const,
		buildLicenses: () => ["extensions", "build", "licenses"] as const,
	},

	fieldTypes: {
		root: () => ["field-types"] as const,
		list: () => ["field-types", "list"] as const,
		split: () => ["field-types", "split"] as const,
		detail: (id: string) => ["field-types", "detail", id] as const,
		schema: (type: string) => ["field-types", "schema", type] as const,
	},

	resources: {
		root: () => ["resources"] as const,
		search: (q: string, type?: string) =>
			type
				? (["resources", "search", q, type] as const)
				: (["resources", "search", q] as const),
		detail: (id: number) => ["resources", "detail", id] as const,
		usage: (id: number) => ["resources", "usage", id] as const,
		metadataFields: () => ["resources", "metadata-fields"] as const,
	},

	resourceFolders: {
		root: () => ["resource-folders"] as const,
		contents: (id: number) => ["resource-folders", "contents", id] as const,
		subfolders: (parent: number) => ["resource-folders", "subfolders", parent] as const,
		flat: () => ["resource-folders", "flat"] as const,
	},

	redirects: {
		root: () => ["404s"] as const,
		sites: () => ["404s", "sites"] as const,
		list: (params: { type: string; page: number; per_page: number; q: string }) =>
			["404s", "list", params] as const,
	},

	system: {
		root: () => ["system"] as const,
		site: () => ["system", "site"] as const,
		backups: () => ["system", "backups"] as const,
		upgradeCheck: () => ["system", "upgrade", "check"] as const,
		/** Pending core DB revision scripts (developer migration gate). */
		migrations: () => ["system", "upgrade", "migrations"] as const,
		status: () => ["system", "status"] as const,
		securityPolicy: () => ["system", "security-policy"] as const,
	},

	configure: {
		root: () => ["configure"] as const,
		fileMetadata: () => ["configure", "file-metadata"] as const,
		geocoding: () => ["configure", "geocoding"] as const,
		email: () => ["configure", "email"] as const,
		cloudStorage: () => ["configure", "cloud-storage"] as const,
		services: () => ["configure", "services"] as const,
		analytics: () => ["configure", "analytics"] as const,
		mediaPresets: () => ["configure", "media-presets"] as const,
		paymentGateway: () => ["configure", "payment-gateway"] as const,
		ai: () => ["configure", "ai"] as const,
	},

	db: {
		root: () => ["db"] as const,
		tables: () => ["db", "tables"] as const,
		columns: (table: string, sort?: boolean) =>
			sort ? (["db", "columns", table, sort] as const) : (["db", "columns", table] as const),
	},

	audit: {
		root: () => ["audit"] as const,
		tables: () => ["audit-tables"] as const,
		list: (params: {
			userFilter?: unknown;
			tableFilter?: unknown;
			start?: unknown;
			end?: unknown;
			via?: unknown;
			page?: number;
		}) => ["audit", params] as const,
	},

	search: {
		/** `opts` distinguishes the federated searches that ask for different types/limits. */
		results: (q: string, opts?: { types?: string[]; limit?: number }) =>
			opts ? (["search", q, opts] as const) : (["search", q] as const),
	},

	ai: {
		root: () => ["ai"] as const,
		conversations: () => ["ai", "conversations"] as const,
		conversation: (id: number) => ["ai", "conversation", id] as const,
	},

	mediaPresets: {
		root: () => ["media-presets"] as const,
	},

	siteIntegrity: {
		root: () => ["site-integrity"] as const,
	},
};
