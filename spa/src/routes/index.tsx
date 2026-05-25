import { Navigate, createBrowserRouter } from "react-router-dom";

import { Dashboard } from "@/pages/Dashboard";
import { Login } from "@/pages/Login";
import { Pages } from "@/pages/Pages";
import { Placeholder } from "@/pages/Placeholder";
import { ProtectedRoute } from "./ProtectedRoute";
import { Shell } from "@/components/shell/Shell";

/**
 * Route tree.
 *
 * /login is public; everything else passes through ProtectedRoute (which
 * boots into the auth refresh probe and redirects to /login on failure) and
 * then Shell (which renders the TopBar + TabNav + active page).
 *
 * The basename is /admin/spa so that in production we serve at
 * https://example.com/admin/spa/* without the SPA fighting the rest of the
 * BigTree admin's URLs. In dev (Vite at :5173) the basename is "/".
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
						{
							index: true,
							element: <Navigate to="/dashboard" replace />,
						},
						{ path: "/dashboard", element: <Dashboard /> },
						{ path: "/pages", element: <Pages /> },
						{
							path: "/modules",
							element: <Placeholder title="Modules" />,
						},
						{
							path: "/files",
							element: <Placeholder title="Files" />,
						},
						{
							path: "/users",
							element: <Placeholder title="Users" />,
						},
						{
							path: "/settings",
							element: <Placeholder title="Settings" />,
						},
						{
							path: "/tags",
							element: <Placeholder title="Tags" />,
						},
						{
							path: "/developer",
							element: <Placeholder title="Developer" />,
						},
					],
				},
			],
		},
		{ path: "*", element: <Navigate to="/" replace /> },
	],
	{
		basename: import.meta.env.PROD ? "/admin/spa" : "/",
	},
);
