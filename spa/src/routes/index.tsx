import { Navigate, Outlet, createBrowserRouter } from "react-router-dom";

import { Dashboard } from "@/pages/Dashboard";
import { Login } from "@/pages/Login";
import { Pages } from "@/pages/Pages";
import { Placeholder } from "@/pages/Placeholder";
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
 *   drill-downs (e.g. /pages, /pages/:parentId, /pages/:id/edit). Phase 0
 *   only sets up the skeleton — most leaf routes are still <Placeholder />
 *   and are replaced as later phases land.
 *
 *   The basename is /admin/spa in production so we live under the existing
 *   PHP admin URL space. In dev (Vite at :5173) the basename is "/".
 */
export const router = createBrowserRouter(
	[
		{ path: "/login", element: <Login /> },
		{
			element: <ProtectedRoute />,
			children: [
				{
					element: <Shell />,
					children: [
						{ index: true, element: <Navigate to="/dashboard" replace /> },

						{ path: "dashboard", element: <Dashboard /> },

						{
							path: "pages",
							children: [
								{ index: true, element: <Pages /> },
								{ path: ":parentId", element: <Pages /> },
								{
									path: ":id/edit",
									element: <Placeholder title="Edit page" />,
									children: [
										{
											path: "revisions",
											element: <Placeholder title="Page revisions" />,
										},
									],
								},
								{
									path: "add/:parentId",
									element: <Placeholder title="Add page" />,
								},
							],
						},

						{
							path: "modules",
							children: [
								{ index: true, element: <Placeholder title="Modules" /> },
								{ path: ":id", element: <Placeholder title="Module" /> },
								{
									path: ":id/view/:sid",
									element: <Placeholder title="Module view" />,
								},
								{
									path: ":id/view/:sid/add",
									element: <Placeholder title="Add entry" />,
								},
								{
									path: ":id/view/:sid/edit/:eid",
									element: <Placeholder title="Edit entry" />,
								},
								{
									path: ":id/report/:sid",
									element: <Placeholder title="Module report" />,
								},
							],
						},

						{
							path: "files",
							children: [
								{ index: true, element: <Placeholder title="Files" /> },
								{
									path: "folder/:id",
									element: <Placeholder title="Folder" />,
								},
							],
						},

						{
							path: "users",
							children: [
								{ index: true, element: <Users /> },
								{ path: ":id/edit", element: <Placeholder title="Edit user" /> },
							],
						},

						{ path: "profile", element: <Placeholder title="Profile" /> },

						{
							path: "settings",
							children: [
								{ index: true, element: <Placeholder title="Settings" /> },
								{
									path: ":id/edit",
									element: <Placeholder title="Edit setting" />,
								},
							],
						},

						{
							path: "tags",
							children: [
								{ index: true, element: <Placeholder title="Tags" /> },
								{ path: "merge", element: <Placeholder title="Merge tags" /> },
							],
						},

						{
							path: "messages",
							children: [
								{ index: true, element: <Placeholder title="Messages" /> },
								{ path: "sent", element: <Placeholder title="Sent messages" /> },
								{ path: ":id", element: <Placeholder title="Message" /> },
							],
						},

						{
							path: "pending-changes/:id",
							element: <Placeholder title="Pending change" />,
						},

						{
							path: "system",
							children: [
								{ path: "404s", element: <Placeholder title="404 manager" /> },
								{
									path: "integrity",
									element: <Placeholder title="Site integrity" />,
								},
							],
						},

						{
							path: "developer",
							element: (
								<RequireLevel level={LEVEL.ADMINISTRATOR}>
									<Outlet />
								</RequireLevel>
							),
							children: [
								{ index: true, element: <Placeholder title="Developer" /> },
								{
									path: "templates",
									children: [
										{
											index: true,
											element: <Placeholder title="Templates" />,
										},
										{
											path: "add",
											element: <Placeholder title="Add template" />,
										},
										{
											path: ":id/edit",
											element: <Placeholder title="Edit template" />,
										},
									],
								},
								{
									path: "callouts",
									children: [
										{ index: true, element: <Placeholder title="Callouts" /> },
										{
											path: "add",
											element: <Placeholder title="Add callout" />,
										},
										{
											path: ":id/edit",
											element: <Placeholder title="Edit callout" />,
										},
									],
								},
								{
									path: "callout-groups",
									element: <Placeholder title="Callout groups" />,
								},
								{
									path: "field-types",
									children: [
										{
											index: true,
											element: <Placeholder title="Field types" />,
										},
										{
											path: "add",
											element: <Placeholder title="Add field type" />,
										},
										{
											path: ":id/edit",
											element: <Placeholder title="Edit field type" />,
										},
									],
								},
								{
									path: "feeds",
									children: [
										{ index: true, element: <Placeholder title="Feeds" /> },
										{
											path: "add",
											element: <Placeholder title="Add feed" />,
										},
										{
											path: ":id/edit",
											element: <Placeholder title="Edit feed" />,
										},
									],
								},
								{
									path: "settings",
									children: [
										{
											index: true,
											element: <Placeholder title="Settings (admin)" />,
										},
										{
											path: "add",
											element: <Placeholder title="Add setting" />,
										},
										{
											path: ":id/edit",
											element: <Placeholder title="Edit setting" />,
										},
									],
								},
								{
									path: "modules",
									children: [
										{
											index: true,
											element: <Placeholder title="Module designer" />,
										},
										{
											path: "add",
											element: <Placeholder title="New module" />,
										},
										{
											path: ":id",
											element: <Placeholder title="Edit module" />,
										},
									],
								},
								{
									path: "module-groups",
									element: <Placeholder title="Module groups" />,
								},
								{
									path: "configure/:area",
									element: <Placeholder title="Configure" />,
								},
								{
									path: "debug/:area",
									element: <Placeholder title="Debug" />,
								},
								{
									path: "extensions",
									element: <Placeholder title="Extensions" />,
								},
								{
									path: "backups",
									element: <Placeholder title="Backups" />,
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
