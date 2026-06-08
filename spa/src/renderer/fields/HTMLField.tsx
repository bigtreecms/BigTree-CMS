import { Editor } from "@tinymce/tinymce-react";
import { useEffect, useRef, useState } from "react";
import type { Editor as TinyMCEEditor } from "tinymce";

import { useAuthStore } from "@/auth/store";

import { settingsOf, type FieldComponentProps } from "./types";

/**
 * Mirrors the legacy admin's two TinyMCE configurations
 * (core/admin/layouts/_html-field-loader.php):
 *
 *   - "full"   — content authoring with images / tables / code view
 *   - "simple" — inline-style editor; bold / italic / underline / link only
 *
 * Per-field choice is driven by:
 *   settings.simple                — boolean override (always simple)
 *   settings.simple_by_permission  — numeric level. If the current user's
 *                                    level is *below* this threshold they get
 *                                    the simple editor.
 *
 * The `template` plugin used by the legacy admin became a TinyMCE Premium-only
 * feature in 6.4, so we omit it from the OSS toolbar here. Everything else
 * matches the original toolbar order so authors don't have to relearn it.
 */
const FULL_PLUGINS = "code anchor image link table visualblocks lists";
const FULL_TOOLBAR =
	"undo redo | styles | bold italic underline | bullist numlist outdent indent | hr anchor link unlink image table | visualblocks code";

const SIMPLE_PLUGINS = "link code visualblocks lists";
const SIMPLE_TOOLBAR = "link unlink bold italic underline removeformat";

interface HTMLFieldSettings {
	simple?: boolean | string | number;
	simple_by_permission?: string | number;
	width?: string | number;
	height?: string | number;
}

/**
 * Track <html data-theme> so the editor's skin matches the rest of the SPA
 * without a page reload when the user toggles dark mode.
 */
const useDocumentTheme = (): "light" | "dark" => {
	const read = () => (document.documentElement.dataset.theme === "dark" ? "dark" : "light");
	const [theme, setTheme] = useState<"light" | "dark">(read);

	useEffect(() => {
		const observer = new MutationObserver(() => {
			setTheme((prev) => {
				const next = read();

				return prev === next ? prev : next;
			});
		});

		observer.observe(document.documentElement, {
			attributes: true,
			attributeFilter: ["data-theme"],
		});

		return () => observer.disconnect();
	}, []);

	return theme;
};

export const HTMLField = ({ field, value, onChange, disabled }: FieldComponentProps) => {
	const settings = settingsOf(field) as HTMLFieldSettings;
	const userLevel = useAuthStore((state) => state.user?.level ?? 0);

	const forcedSimple = Boolean(settings.simple);
	const thresholdRaw = Number(settings.simple_by_permission);
	const threshold = Number.isFinite(thresholdRaw) ? thresholdRaw : 0;
	const isSimple = forcedSimple || (threshold > 0 && threshold > userLevel);

	const theme = useDocumentTheme();
	const dark = theme === "dark";

	// `@tinymce/tinymce-react` only translates the `disabled` prop into TinyMCE's
	// `readonly` *mode*, which in TinyMCE 7 leaves the toolbar interactive and the
	// surface only partially locked. We drive the dedicated `disabled` editor
	// option instead (added in TinyMCE 7.6) — it greys out the whole editor and
	// fully blocks editing — both at init and live as the lock state changes.
	const editorRef = useRef<TinyMCEEditor | null>(null);

	useEffect(() => {
		const editor = editorRef.current;

		if (editor && editor.initialized) {
			editor.options.set("disabled", Boolean(disabled));
		}
	}, [disabled]);

	const text = typeof value === "string" ? value : value == null ? "" : String(value);

	// `import.meta.env.BASE_URL` is "/" in dev (assets served via the public
	// dir) and "/admin/spa/" in production builds. Either way the static-copy
	// plugin puts the editor at `${base}tinymce/tinymce.min.js`.
	const scriptSrc = `${import.meta.env.BASE_URL}tinymce/tinymce.min.js`.replace(/\/{2,}/g, "/");

	return (
		<Editor
			// Re-mount when skin / variant flips so TinyMCE picks up the new init
			// (`init` is only consumed once per editor instance).
			key={`${theme}-${isSimple ? "simple" : "full"}`}
			tinymceScriptSrc={scriptSrc}
			licenseKey="gpl"
			value={text}
			disabled={disabled}
			onInit={(_evt, editor) => {
				editorRef.current = editor;
				editor.options.set("disabled", Boolean(disabled));
			}}
			onEditorChange={(html) => onChange(html)}
			init={{
				disabled: Boolean(disabled),
				menubar: false,
				plugins: isSimple ? SIMPLE_PLUGINS : FULL_PLUGINS,
				toolbar: isSimple ? SIMPLE_TOOLBAR : FULL_TOOLBAR,
				skin: dark ? "oxide-dark" : "oxide",
				content_css: dark ? "dark" : "default",
				browser_spellcheck: true,
				relative_urls: false,
				remove_script_host: false,
				convert_urls: false,
				extended_valid_elements: "*[*]",
				branding: false,
				promotion: false,
				statusbar: !isSimple,
				resize: true,
				width: settings.width ?? undefined,
				height: settings.height ?? (isSimple ? 180 : 360),
				// File / image pickers are wired up in the Image / Upload field
				// work — for now the dialogs fall back to a plain URL field.
			}}
		/>
	);
};
