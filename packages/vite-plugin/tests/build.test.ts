import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { afterEach, beforeEach, describe, expect, it } from "vitest";
import { build } from "vite";
import { foehn } from "../src/plugin.js";

/**
 * A real build of a theme entry with a dynamic import.
 *
 * Vite writes the dependencies of a dynamic import into the bundle, and preloads them
 * with `<link rel="modulepreload">` from the URL it builds with `base`. The theme is
 * served from `/wp-content/themes/<theme>/dist/`, not from the site root.
 */
/**
 * The built code of the `src/app.js` entry, found through the manifest.
 */
function entryCode(themeDir: string): string {
    const manifest = JSON.parse(readFileSync(join(themeDir, "dist/.vite/manifest.json"), "utf8"));

    return readFileSync(join(themeDir, "dist", manifest["src/app.js"].file), "utf8");
}

describe("foehn build", () => {
    let themeDir: string;

    beforeEach(() => {
        themeDir = mkdtempSync(join(tmpdir(), "foehn-vite-build-"));
        mkdirSync(join(themeDir, "src"));
        writeFileSync(join(themeDir, "src/app.js"), 'import("./lazy.js").then((m) => m.run());\n');
        writeFileSync(
            join(themeDir, "src/lazy.js"),
            'import "./shared.js";\nexport const run = () => 1;\n',
        );
        writeFileSync(join(themeDir, "src/shared.js"), "export const shared = 1;\n");
    });

    afterEach(() => {
        rmSync(themeDir, { recursive: true, force: true });
    });

    it("preloads the chunks of a dynamic import relative to the bundle, not from the site root", async () => {
        await build({
            root: themeDir,
            configFile: false,
            logLevel: "silent",
            plugins: [foehn({ input: "src/app.js", themeDir })],
        });

        // With `base: '/'`, the preload helper returns `/` + `assets/lazy-….js`, a URL
        // on the site root that answers 404.
        expect(entryCode(themeDir)).not.toMatch(/return["'`]\/["'`]\+/);
    });

    it("keeps the base of a project that sets one", async () => {
        await build({
            root: themeDir,
            configFile: false,
            logLevel: "silent",
            base: "/static/",
            plugins: [foehn({ input: "src/app.js", themeDir })],
        });

        expect(entryCode(themeDir)).toMatch(/return["'`]\/static\/["'`]\+/);
    });
});
