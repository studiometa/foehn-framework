import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { foehn } from "../src/plugin.js";
import type { Plugin } from "vite";

// Mock utils
vi.mock("../src/utils/index.js", () => ({
    resolveGlobPatterns: vi.fn().mockResolvedValue({
        "src/app": "/project/src/app.js",
    }),
    detectDdev: vi.fn().mockResolvedValue(null),
    getDdevSiteUrl: vi.fn().mockReturnValue("https://test.ddev.site"),
    writeHotFile: vi.fn().mockResolvedValue(undefined),
    removeHotFile: vi.fn().mockResolvedValue(undefined),
}));

describe("foehn", () => {
    beforeEach(() => {
        vi.resetAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("returns an array of plugins", () => {
        const plugins = foehn({ input: "src/app.js" });

        expect(Array.isArray(plugins)).toBe(true);
        expect(plugins.length).toBe(2);
    });

    it("creates main plugin with correct name", () => {
        const plugins = foehn({ input: "src/app.js" });
        const mainPlugin = plugins[0] as Plugin;

        expect(mainPlugin.name).toBe("foehn");
    });

    it("creates reload plugin with correct name", () => {
        const plugins = foehn({ input: "src/app.js" });
        const reloadPlugin = plugins[1] as Plugin;

        expect(reloadPlugin.name).toBe("foehn:reload");
    });

    it("main plugin is enforced as pre", () => {
        const plugins = foehn({ input: "src/app.js" });
        const mainPlugin = plugins[0] as Plugin;

        expect(mainPlugin.enforce).toBe("pre");
    });
});

describe("foehn config hook", () => {
    beforeEach(() => {
        vi.resetAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("configures build manifest", async () => {
        const plugins = foehn({ input: "src/app.js" });
        const mainPlugin = plugins[0] as Plugin;
        const configHook = mainPlugin.config as Function;

        const result = await configHook({}, { command: "build" });

        expect(result.build.manifest).toBe(true);
    });

    it("configures rollup input from resolved patterns", async () => {
        const { resolveGlobPatterns } = await import("../src/utils/index.js");
        vi.mocked(resolveGlobPatterns).mockResolvedValue({
            "src/app": "/project/src/app.js",
            "src/vendor": "/project/src/vendor.js",
        });

        const plugins = foehn({ input: ["src/app.js", "src/vendor.js"] });
        const mainPlugin = plugins[0] as Plugin;
        const configHook = mainPlugin.config as Function;

        const result = await configHook({}, { command: "build" });

        expect(result.build.rollupOptions.input).toEqual({
            "src/app": "/project/src/app.js",
            "src/vendor": "/project/src/vendor.js",
        });
    });

    it("configures outDir from options", async () => {
        const plugins = foehn({ input: "src/app.js", outDir: "build" });
        const mainPlugin = plugins[0] as Plugin;
        const configHook = mainPlugin.config as Function;

        const result = await configHook({}, { command: "build" });

        expect(result.build.outDir).toContain("build");
    });

    it("configures proxy when DDEV is detected", async () => {
        const { detectDdev, getDdevSiteUrl } = await import("../src/utils/index.js");
        vi.mocked(detectDdev).mockResolvedValue({ name: "my-project" });
        vi.mocked(getDdevSiteUrl).mockReturnValue("https://my-project.ddev.site");

        const plugins = foehn({ input: "src/app.js" });
        const mainPlugin = plugins[0] as Plugin;
        const configHook = mainPlugin.config as Function;

        const result = await configHook({}, { command: "serve" });

        expect(result.server?.proxy).toBeDefined();
    });

    it("does not configure proxy when DDEV is not detected", async () => {
        const { detectDdev } = await import("../src/utils/index.js");
        vi.mocked(detectDdev).mockResolvedValue(null);

        const plugins = foehn({ input: "src/app.js" });
        const mainPlugin = plugins[0] as Plugin;
        const configHook = mainPlugin.config as Function;

        const result = await configHook({}, { command: "serve" });

        expect(result.server).toBeUndefined();
    });
});
