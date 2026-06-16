import { Suspense, lazy } from "react";
import type { ComponentType } from "react";
import { Navigate, Outlet, createBrowserRouter } from "react-router-dom";

import { RouteFallback } from "./RouteFallback";

/**
 * The entire developer section is gated behind LEVEL.DEVELOPER and visited far
 * less often than the editorial pages, so its page modules are code-split into
 * their own chunks via React.lazy. The router stores a `default` export, so each
 * named-export page is re-exported through `.then(...)`. A shared `lazyDev`
 * helper keeps the mapping terse. Frequently-used top-level pages (Dashboard,
 * Pages, Modules, Files, …) stay eager so primary navigation is not delayed.
 */
const lazyDev = <T,>(loader: () => Promise<T>, name: keyof T) => {
	return lazy(() =>
		loader().then((module) => ({
			default: module[name] as ComponentType,
		}))
	);
};

const CalloutEdit = lazyDev(() => import("@/pages/developer/CalloutEdit"), "CalloutEdit");
const CalloutGroupEdit = lazyDev(
	() => import("@/pages/developer/CalloutGroupEdit"),
	"CalloutGroupEdit"
);
const CalloutGroups = lazyDev(() => import("@/pages/developer/CalloutGroups"), "CalloutGroups");
const Callouts = lazyDev(() => import("@/pages/developer/Callouts"), "Callouts");
const ConfigureAnalytics = lazyDev(
	() => import("@/pages/developer/configure/ConfigureAnalytics"),
	"ConfigureAnalytics"
);
const ConfigureCloudStorage = lazyDev(
	() => import("@/pages/developer/configure/ConfigureCloudStorage"),
	"ConfigureCloudStorage"
);
const ConfigureEmail = lazyDev(
	() => import("@/pages/developer/configure/ConfigureEmail"),
	"ConfigureEmail"
);
const ConfigureFileMetadata = lazyDev(
	() => import("@/pages/developer/configure/ConfigureFileMetadata"),
	"ConfigureFileMetadata"
);
const ConfigureGeocoding = lazyDev(
	() => import("@/pages/developer/configure/ConfigureGeocoding"),
	"ConfigureGeocoding"
);
const ConfigureIndex = lazyDev(
	() => import("@/pages/developer/configure/ConfigureIndex"),
	"ConfigureIndex"
);
const ConfigureMediaPresets = lazyDev(
	() => import("@/pages/developer/configure/ConfigureMediaPresets"),
	"ConfigureMediaPresets"
);
const ConfigurePaymentGateway = lazyDev(
	() => import("@/pages/developer/configure/ConfigurePaymentGateway"),
	"ConfigurePaymentGateway"
);
const ConfigureServices = lazyDev(
	() => import("@/pages/developer/configure/ConfigureServices"),
	"ConfigureServices"
);
const Backups = lazyDev(() => import("@/pages/developer/Backups"), "Backups");
const DebugAudit = lazyDev(() => import("@/pages/developer/debug/DebugAudit"), "DebugAudit");
const DebugEmulator = lazyDev(
	() => import("@/pages/developer/debug/DebugEmulator"),
	"DebugEmulator"
);
const DebugIndex = lazyDev(() => import("@/pages/developer/debug/DebugIndex"), "DebugIndex");
const DebugSecurity = lazyDev(
	() => import("@/pages/developer/debug/DebugSecurity"),
	"DebugSecurity"
);
const DebugStatus = lazyDev(() => import("@/pages/developer/debug/DebugStatus"), "DebugStatus");
const DebugUpgrade = lazyDev(() => import("@/pages/developer/debug/DebugUpgrade"), "DebugUpgrade");
const Developer = lazyDev(() => import("@/pages/developer/Developer"), "Developer");
const DeveloperSettings = lazyDev(
	() => import("@/pages/developer/DeveloperSettings"),
	"DeveloperSettings"
);
const Extensions = lazyDev(() => import("@/pages/developer/Extensions"), "Extensions");
const ExtensionInstall = lazyDev(
	() => import("@/pages/developer/ExtensionInstall"),
	"ExtensionInstall"
);
const ExtensionBuild = lazyDev(() => import("@/pages/developer/ExtensionBuild"), "ExtensionBuild");
const FeedEdit = lazyDev(() => import("@/pages/developer/FeedEdit"), "FeedEdit");
const Feeds = lazyDev(() => import("@/pages/developer/Feeds"), "Feeds");
const FieldTypeEdit = lazyDev(() => import("@/pages/developer/FieldTypeEdit"), "FieldTypeEdit");
const FieldTypes = lazyDev(() => import("@/pages/developer/FieldTypes"), "FieldTypes");
const ModuleDesigner = lazyDev(() => import("@/pages/developer/ModuleDesigner"), "ModuleDesigner");
const ModuleDesignerEdit = lazyDev(
	() => import("@/pages/developer/ModuleDesignerEdit"),
	"ModuleDesignerEdit"
);
const ModuleGroupEdit = lazyDev(
	() => import("@/pages/developer/ModuleGroupEdit"),
	"ModuleGroupEdit"
);
const ModuleGroups = lazyDev(() => import("@/pages/developer/ModuleGroups"), "ModuleGroups");
const SettingConfigure = lazyDev(
	() => import("@/pages/developer/SettingConfigure"),
	"SettingConfigure"
);
const TemplateEdit = lazyDev(() => import("@/pages/developer/TemplateEdit"), "TemplateEdit");
const Templates = lazyDev(() => import("@/pages/developer/Templates"), "Templates");

import { Analytics } from "@/pages/Analytics";
import { Dashboard } from "@/pages/Dashboard";
import { EmbedForm } from "@/pages/EmbedForm";
import { Create301 } from "@/pages/Create301";
import { Files } from "@/pages/Files";
import { FourOhFours } from "@/pages/FourOhFours";
import { Import301 } from "@/pages/Import301";
import { Login } from "@/pages/Login";
import { ForgotPassword } from "@/pages/ForgotPassword";
import { ResetPassword } from "@/pages/ResetPassword";
import { MessageThread } from "@/pages/MessageThread";
import { Messages } from "@/pages/Messages";
import { ModuleDispatcher } from "@/pages/ModuleDispatcher";
import { ModuleLayout } from "@/pages/ModuleLayout";
import { Modules } from "@/pages/Modules";
import { PageAdd } from "@/pages/PageAdd";
import { PageEdit } from "@/pages/PageEdit";
import { PageRevisions } from "@/pages/PageRevisions";
import { Pages } from "@/pages/Pages";
import { PendingChangeDetail } from "@/pages/PendingChangeDetail";
import { PendingChanges } from "@/pages/PendingChanges";
import { SettingEdit } from "@/pages/SettingEdit";
import { Settings } from "@/pages/Settings";
import { SiteIntegrity } from "@/pages/SiteIntegrity";
import { Profile } from "@/pages/Profile";
import { TagMerge } from "@/pages/TagMerge";
import { Tags } from "@/pages/Tags";
import { UserEdit } from "@/pages/UserEdit";
import { Users } from "@/pages/Users";
import { ProtectedRoute } from "./ProtectedRoute";
import { Shell } from "@/components/shell/Shell";
import { RequireLevel } from "@/components/ui/AccessDenied";
import { LEVEL } from "@/lib/permissions";

/**
 * Route tree.
 *
 *   /login is public. Everything else passes through ProtectedRoute (which
 *   boots into the auth refresh probe and redirects to /login on failure)
 *   and then <Shell /> (TopBar + TabNav + active page).
 *
 *   Each top-level section is its own subtree with nested children for
 *   drill-downs (e.g. /pages, /pages/:parentId, /pages/:id/edit).
 *
 *   The basename is /admin/spa in production so we live under the existing
 *   PHP admin URL space. In dev (Vite at :5173) the basename is "/".
 */
export const router = createBrowserRouter(
	[
		{ path: "/login", element: <Login /> },
		{ path: "/login/forgot", element: <ForgotPassword /> },
		{ path: "/login/reset/:token", element: <ResetPassword /> },
		{ path: "/embed/:hash", element: <EmbedForm /> },
		{
			element: <ProtectedRoute />,
			children: [
				{
					element: <Shell />,
					children: [
						{ index: true, element: <Navigate to="/dashboard" replace /> },

						{
							path: "dashboard",
							children: [
								{ index: true, element: <Dashboard /> },
								{
									path: "404s",
									element: (
										<RequireLevel level={LEVEL.ADMINISTRATOR}>
											<Outlet />
										</RequireLevel>
									),
									children: [
										{ index: true, element: <FourOhFours type="404" /> },
										{
											path: "ignored",
											element: <FourOhFours type="ignored" />,
										},
										{ path: "301", element: <FourOhFours type="301" /> },
										{ path: "301/add", element: <Create301 /> },
										{ path: "301/import", element: <Import301 /> },
									],
								},
								{
									path: "integrity",
									element: (
										<RequireLevel level={LEVEL.ADMINISTRATOR}>
											<SiteIntegrity />
										</RequireLevel>
									),
								},
							],
						},

						{
							path: "analytics",
							element: (
								<RequireLevel level={LEVEL.ADMINISTRATOR}>
									<Analytics />
								</RequireLevel>
							),
						},

						{
							path: "pages",
							children: [
								{ index: true, element: <Pages /> },
								{ path: ":parentId", element: <Pages /> },
								{
									path: ":id/edit",
									element: <PageEdit />,
								},
								{
									path: "draft/:pcid/edit",
									element: <PageEdit />,
								},
								{
									path: ":id/edit/revisions",
									element: <PageRevisions />,
								},
								{
									path: "add/:parentId",
									element: <PageAdd />,
								},
							],
						},

						{
							path: "modules",
							children: [
								{ index: true, element: <Modules /> },
								{
									path: ":moduleRoute",
									element: <ModuleLayout />,
									children: [
										{ index: true, element: <ModuleDispatcher /> },
										{ path: "*", element: <ModuleDispatcher /> },
									],
								},
							],
						},

						{
							path: "files",
							children: [
								{ index: true, element: <Files /> },
								{ path: "folder/:id", element: <Files /> },
							],
						},

						{
							path: "users",
							element: (
								<RequireLevel level={LEVEL.ADMINISTRATOR}>
									<Outlet />
								</RequireLevel>
							),
							children: [
								{ index: true, element: <Users /> },
								{ path: ":id/edit", element: <UserEdit /> },
							],
						},

						{ path: "profile", element: <Profile /> },

						{
							path: "settings",
							element: (
								<RequireLevel level={LEVEL.ADMINISTRATOR}>
									<Outlet />
								</RequireLevel>
							),
							children: [
								{ index: true, element: <Settings /> },
								{ path: ":id/edit", element: <SettingEdit /> },
							],
						},

						{
							path: "tags",
							element: (
								<RequireLevel level={LEVEL.ADMINISTRATOR}>
									<Outlet />
								</RequireLevel>
							),
							children: [
								{ index: true, element: <Tags /> },
								{ path: "merge", element: <TagMerge /> },
							],
						},

						{
							path: "messages",
							children: [
								{ index: true, element: <Messages /> },
								{ path: "sent", element: <Messages /> },
								{ path: ":id", element: <MessageThread /> },
							],
						},

						{
							path: "pending-changes",
							children: [
								{ index: true, element: <PendingChanges /> },
								{ path: ":id", element: <PendingChangeDetail /> },
							],
						},

						{
							path: "developer",
							element: (
								<RequireLevel level={LEVEL.DEVELOPER}>
									<Suspense fallback={<RouteFallback />}>
										<Outlet />
									</Suspense>
								</RequireLevel>
							),
							children: [
								{ index: true, element: <Developer /> },
								{
									path: "templates",
									children: [
										{ index: true, element: <Templates /> },
										{ path: "add", element: <TemplateEdit /> },
										{ path: ":id/edit", element: <TemplateEdit /> },
									],
								},
								{
									path: "callouts",
									children: [
										{ index: true, element: <Callouts /> },
										{ path: "add", element: <CalloutEdit /> },
										{ path: ":id/edit", element: <CalloutEdit /> },
									],
								},
								{
									path: "callout-groups",
									children: [
										{ index: true, element: <CalloutGroups /> },
										{ path: "add", element: <CalloutGroupEdit /> },
										{ path: ":id/edit", element: <CalloutGroupEdit /> },
									],
								},
								{
									path: "field-types",
									children: [
										{ index: true, element: <FieldTypes /> },
										{ path: "add", element: <FieldTypeEdit /> },
										{ path: ":id/edit", element: <FieldTypeEdit /> },
									],
								},
								{
									path: "feeds",
									children: [
										{ index: true, element: <Feeds /> },
										{ path: "add", element: <FeedEdit /> },
										{ path: ":id/edit", element: <FeedEdit /> },
									],
								},
								{
									path: "settings",
									children: [
										{ index: true, element: <DeveloperSettings /> },
										{ path: "add", element: <SettingConfigure /> },
										{ path: ":id/edit", element: <SettingConfigure /> },
									],
								},
								{
									path: "modules",
									children: [
										{ index: true, element: <ModuleDesigner /> },
										{ path: "add", element: <ModuleDesignerEdit /> },
										{ path: ":id", element: <ModuleDesignerEdit /> },
									],
								},
								{
									path: "module-groups",
									children: [
										{ index: true, element: <ModuleGroups /> },
										{ path: "add", element: <ModuleGroupEdit /> },
										{ path: ":id/edit", element: <ModuleGroupEdit /> },
									],
								},
								{
									path: "configure",
									children: [
										{ index: true, element: <ConfigureIndex /> },
										{ path: "email", element: <ConfigureEmail /> },
										{ path: "geocoding", element: <ConfigureGeocoding /> },
										{
											path: "cloud-storage",
											element: <ConfigureCloudStorage />,
										},
										{
											path: "payment-gateway",
											element: <ConfigurePaymentGateway />,
										},
										{ path: "analytics", element: <ConfigureAnalytics /> },
										{ path: "services", element: <ConfigureServices /> },
										{
											path: "media-presets",
											element: <ConfigureMediaPresets />,
										},
										{
											path: "file-metadata",
											element: <ConfigureFileMetadata />,
										},
									],
								},
								{
									path: "debug",
									children: [
										{ index: true, element: <DebugIndex /> },
										{ path: "status", element: <DebugStatus /> },
										{ path: "security", element: <DebugSecurity /> },
										{ path: "audit", element: <DebugAudit /> },
										{ path: "emulator", element: <DebugEmulator /> },
										{ path: "upgrade", element: <DebugUpgrade /> },
									],
								},
								{
									path: "extensions",
									element: <Extensions />,
								},
								{
									path: "extensions/install",
									element: <ExtensionInstall />,
								},
								{
									path: "extensions/build",
									element: <ExtensionBuild />,
								},
								{
									path: "backups",
									element: <Backups />,
								},
							],
						},
					],
				},
			],
		},
		{ path: "*", element: <Navigate to="/" replace /> },
	],
	{
		basename: import.meta.env.PROD ? "/admin/spa" : "/",
	}
);
