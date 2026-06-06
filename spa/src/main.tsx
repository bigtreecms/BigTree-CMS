import "@/styles/index.css";

import { App } from "./App";
import { StrictMode } from "react";
import { bootstrapTheme } from "@/lib/theme";
import { createRoot } from "react-dom/client";
import { registerSdk } from "@/sdk/registerSdk";

// Apply theme + density + accent BEFORE React mounts so there's no flash.
bootstrapTheme();

// Publish the SDK singletons custom action modules resolve their imports against.
registerSdk();

const root = document.getElementById("root");
if (!root) throw new Error("Missing #root in index.html");

createRoot(root).render(
	<StrictMode>
		<App />
	</StrictMode>
);
