import { Navigate, Outlet, createBrowserRouter } from "react-router-dom";

import { CalloutEdit } from "@/pages/developer/CalloutEdit";
import { CalloutGroupEdit } from "@/pages/developer/CalloutGroupEdit";
import { CalloutGroups } from "@/pages/developer/CalloutGroups";
import { Callouts } from "@/pages/developer/Callouts";
import { ConfigureAnalytics } from "@/pages/developer/configure/ConfigureAnalytics";
import { ConfigureCloudStorage } from "@/pages/developer/configure/ConfigureCloudStorage";
import { ConfigureEmail } from "@/pages/developer/configure/ConfigureEmail";
import { ConfigureFileMetadata } from "@/pages/developer/configure/ConfigureFileMetadata";
import { ConfigureGeocoding } from "@/pages/developer/configure/ConfigureGeocoding";
import { ConfigureIndex } from "@/pages/developer/configure/ConfigureIndex";
import { ConfigureMediaPresets } from "@/pages/developer/configure/ConfigureMediaPresets";
import { ConfigurePaymentGateway } from "@/pages/developer/configure/ConfigurePaymentGateway";
import { ConfigureServices } from "@/pages/developer/configure/ConfigureServices";
import { Analytics } from "@/pages/Analytics";
import { Dashboard } from "@/pages/Dashboard";
import { EmbedForm } from "@/pages/EmbedForm";
import { Backups } from "@/pages/developer/Backups";
import { DebugAudit } from "@/pages/developer/debug/DebugAudit";
import { DebugEmulator } from "@/pages/developer/debug/DebugEmulator";
import { DebugIndex } from "@/pages/developer/debug/DebugIndex";
import { DebugSecurity } from "@/pages/developer/debug/DebugSecurity";
import { DebugStatus } from "@/pages/developer/debug/DebugStatus";
import { DebugUpgrade } from "@/pages/developer/debug/DebugUpgrade";
import { Developer } from "@/pages/developer/Developer";
import { DeveloperSettings } from "@/pages/developer/DeveloperSettings";
import { Extensions } from "@/pages/developer/Extensions";
import { ExtensionInstall } from "@/pages/developer/ExtensionInstall";
import { ExtensionBuild } from "@/pages/developer/ExtensionBuild";
import { FeedEdit } from "@/pages/developer/FeedEdit";
import { Feeds } from "@/pages/developer/Feeds";
import { FieldTypeEdit } from "@/pages/developer/FieldTypeEdit";
import { FieldTypes } from "@/pages/developer/FieldTypes";
import { Create301 } from "@/pages/Create301";
import { Files } from "@/pages/Files";
import { FourOhFours } from "@/pages/FourOhFours";
import { Import301 } from "@/pages/Import301";
import { Login } from "@/pages/Login";
import { MessageThread } from "@/pages/MessageThread";
import { Messages } from "@/pages/Messages";
import { ModuleDesigner } from "@/pages/developer/ModuleDesigner";
import { ModuleDesignerEdit } from "@/pages/developer/ModuleDesignerEdit";
import { ModuleGroupEdit } from "@/pages/developer/ModuleGroupEdit";
import { ModuleGroups } from "@/pages/developer/ModuleGroups";
import { SettingConfigure } from "@/pages/developer/SettingConfigure";
import { TemplateEdit } from "@/pages/developer/TemplateEdit";
import { Templates } from "@/pages/developer/Templates";
import { ModuleEntry } from "@/pages/ModuleEntry";
import { ModuleAction } from "@/pages/ModuleAction";
import { ModuleEntryAdd } from "@/pages/ModuleEntryAdd";
import { ModuleEntryEdit } from "@/pages/ModuleEntryEdit";
import { ModuleLayout } from "@/pages/ModuleLayout";
import { ModuleReport } from "@/pages/ModuleReport";
import { Modules } from "@/pages/Modules";
import { ModuleView } from "@/pages/ModuleView";
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
									path: ":id",
									element: <ModuleLayout />,
									children: [
										{ index: true, element: <ModuleEntry /> },
										{ path: "view/:sid", element: <ModuleView /> },
										{ path: "view/:sid/add", element: <ModuleEntryAdd /> },
										{
											path: "view/:sid/edit/:eid",
											element: <ModuleEntryEdit />,
										},
										{ path: "report/:sid", element: <ModuleReport /> },
										{ path: "action/:sid", element: <ModuleAction /> },
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
									<Outlet />
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
