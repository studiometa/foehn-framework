import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { resolve } from "node:path";

// We need to mock fast-glob before importing the module that uses it
vi.mock("fast-glob", () => ({
    glob: vi.fn(),
}));

// Import after mocking
import { resolveGlobPatterns } from "../../src/utils/glob.js";
import { glob } from "fast-glob";

describe("resolveGlobPatterns", () => {
    beforeEach(() => {
        vi.resetAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("resolves direct file paths without calling glob", async () => {
        const result = await resolveGlobPatterns(["src/app.js"], "/project");

        expect(result).toEqual({
            "src/app": resolve("/project", "src/app.js"),
        });
        expect(glob).not.toHaveBeenCalled();
    });

    it("resolves multiple direct paths with different names", async () => {
        const result = await resolveGlobPatterns(["src/app.js", "src/styles.css"], "/project");

        expect(result).toEqual({
            "src/app": resolve("/project", "src/app.js"),
            "src/styles": resolve("/project", "src/styles.css"),
        });
    });

    it("overwrites when same name with different extension", async () => {
        // This is expected behavior - last one wins
        const result = await resolveGlobPatterns(["src/app.js", "src/app.css"], "/project");

        expect(result["src/app"]).toBe(resolve("/project", "src/app.css"));
    });

    it("resolves glob patterns with *", async () => {
        vi.mocked(glob).mockResolvedValue(["/project/src/app.js", "/project/src/vendor.js"]);

        const result = await resolveGlobPatterns(["src/*.js"], "/project");

        expect(glob).toHaveBeenCalledWith("src/*.js", { cwd: "/project", absolute: true });
        expect(result).toEqual({
            "src/app": "/project/src/app.js",
            "src/vendor": "/project/src/vendor.js",
        });
    });

    it("resolves glob patterns with **", async () => {
        vi.mocked(glob).mockResolvedValue([
            "/project/src/js/app.js",
            "/project/src/js/pages/home.js",
        ]);

        const result = await resolveGlobPatterns(["src/**/*.js"], "/project");

        expect(result).toEqual({
            "src/js/app": "/project/src/js/app.js",
            "src/js/pages/home": "/project/src/js/pages/home.js",
        });
    });

    it("resolves glob patterns with ?", async () => {
        vi.mocked(glob).mockResolvedValue(["/project/src/app1.js", "/project/src/app2.js"]);

        await resolveGlobPatterns(["src/app?.js"], "/project");

        expect(glob).toHaveBeenCalledWith("src/app?.js", { cwd: "/project", absolute: true });
    });

    it("resolves glob patterns with braces", async () => {
        vi.mocked(glob).mockResolvedValue(["/project/src/app.js", "/project/src/app.ts"]);

        await resolveGlobPatterns(["src/app.{js,ts}"], "/project");

        expect(glob).toHaveBeenCalledWith("src/app.{js,ts}", {
            cwd: "/project",
            absolute: true,
        });
    });

    it("handles mixed patterns", async () => {
        vi.mocked(glob).mockResolvedValue([
            "/project/src/pages/home.js",
            "/project/src/pages/about.js",
        ]);

        const result = await resolveGlobPatterns(["src/app.js", "src/pages/*.js"], "/project");

        expect(result).toEqual({
            "src/app": resolve("/project", "src/app.js"),
            "src/pages/home": "/project/src/pages/home.js",
            "src/pages/about": "/project/src/pages/about.js",
        });
    });
});
