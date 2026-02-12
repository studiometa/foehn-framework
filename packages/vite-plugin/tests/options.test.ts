import { describe, it, expect } from "vitest";
import { resolveOptions, defaultOptions } from "../src/options.js";

describe("resolveOptions", () => {
    it("normalizes string input to array", () => {
        const result = resolveOptions({ input: "src/app.js" });
        expect(result.input).toEqual(["src/app.js"]);
    });

    it("keeps array input as-is", () => {
        const result = resolveOptions({ input: ["src/app.js", "src/app.css"] });
        expect(result.input).toEqual(["src/app.js", "src/app.css"]);
    });

    it("applies default reload patterns", () => {
        const result = resolveOptions({ input: "src/app.js" });
        expect(result.reload).toEqual(defaultOptions.reload);
    });

    it("normalizes string reload to array", () => {
        const result = resolveOptions({
            input: "src/app.js",
            reload: "app/**/*.php",
        });
        expect(result.reload).toEqual(["app/**/*.php"]);
    });

    it("keeps array reload as-is", () => {
        const result = resolveOptions({
            input: "src/app.js",
            reload: ["app/**/*.php", "templates/**/*.twig"],
        });
        expect(result.reload).toEqual(["app/**/*.php", "templates/**/*.twig"]);
    });

    it("applies default outDir", () => {
        const result = resolveOptions({ input: "src/app.js" });
        expect(result.outDir).toBe("dist");
    });

    it("allows custom outDir", () => {
        const result = resolveOptions({ input: "src/app.js", outDir: "build" });
        expect(result.outDir).toBe("build");
    });

    it("applies default themeDir", () => {
        const result = resolveOptions({ input: "src/app.js" });
        expect(result.themeDir).toBe(process.cwd());
    });

    it("allows custom themeDir", () => {
        const result = resolveOptions({ input: "src/app.js", themeDir: "/path/to/theme" });
        expect(result.themeDir).toBe("/path/to/theme");
    });

    it("applies default hotFile", () => {
        const result = resolveOptions({ input: "src/app.js" });
        expect(result.hotFile).toBe("hot");
    });

    it("allows custom hotFile", () => {
        const result = resolveOptions({ input: "src/app.js", hotFile: ".hot" });
        expect(result.hotFile).toBe(".hot");
    });
});
