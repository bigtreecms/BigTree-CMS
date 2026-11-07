import { describe, expect, it } from "vitest";

import { renderHook } from "@testing-library/react";

import { useEmbedUploadTarget } from "@/renderer/embed/useEmbedUploadTarget";
import { scopeUploadTarget } from "@/renderer/fields/FieldUploadContext";

describe("useEmbedUploadTarget", () => {
	it("points uploads at the form's public routes with the library off", () => {
		const { result } = renderHook(() => useEmbedUploadTarget("abc123", "chal"));
		const { target } = result.current;

		expect(target.imagePath).toBe("/embed-forms/abc123/images");
		expect(target.filePath).toBe("/embed-forms/abc123/files");
		expect(target.libraryAccess).toBe(false);
		expect(target.extraFor("photo")).toEqual({ column: "photo", challenge: "chal" });
	});

	it("sends a claim token only while the form still holds that file", () => {
		const { result } = renderHook(() => useEmbedUploadTarget("abc123", "chal"));
		const { target, tokensFor } = result.current;

		target.onStored?.("photo", { file: "{wwwroot}files/a.jpg", token: "t-photo" });
		target.onStored?.("resume", { file: "{wwwroot}files/cv.pdf", token: "t-resume" });

		expect(tokensFor({ photo: "{wwwroot}files/a.jpg", resume: "", name: "Jo" })).toEqual([
			"t-photo",
		]);
	});

	it("keeps a token per row for uploads inside a matrix", () => {
		const { result } = renderHook(() => useEmbedUploadTarget("abc123", "chal"));
		const { target, tokensFor } = result.current;
		const scoped = scopeUploadTarget(target, "gallery");

		expect(scoped.extraFor("shot")).toEqual({ column: "gallery.shot", challenge: "chal" });
		expect(scopeUploadTarget(scoped, "inner").extraFor("deep").column).toBe(
			"gallery.inner.deep"
		);

		scoped.onStored?.("shot", { file: "{wwwroot}files/pages/1.jpg", token: "t-1" });
		scoped.onStored?.("shot", { file: "{wwwroot}files/pages/2.jpg", token: "t-2" });
		scoped.onStored?.("shot", { file: "{wwwroot}files/pages/3.jpg", token: "t-3" });

		expect(
			tokensFor({
				gallery: [
					{ shot: "{wwwroot}files/pages/1.jpg" },
					{ shot: "{wwwroot}files/pages/2.jpg" },
				],
			})
		).toEqual(["t-1", "t-2"]);
	});

	it("refuses a crop without a signed spec", async () => {
		const { result } = renderHook(() => useEmbedUploadTarget("abc123", "chal"));
		const crop = {
			center_crops: [],
			directory: "files/",
			grayscale: false,
			height: 100,
			name: "a.jpg",
			prefix: "sm_",
			retina: false,
			thumbs: [],
			width: 100,
		};

		await expect(
			result.current.target.crop(crop, "a.jpg", { x: 0, y: 0, width: 10, height: 10 })
		).rejects.toThrow();
	});
});
