import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { App } from "./App";
import { bootstrapTheme } from "@/lib/theme";
import "@/styles/index.css";

// Apply theme + density + accent BEFORE React mounts so there's no flash.
bootstrapTheme();

const root = document.getElementById("root");
if (!root) throw new Error("Missing #root in index.html");

createRoot(root).render(
	<StrictMode>
		<App />
	</StrictMode>,
);
