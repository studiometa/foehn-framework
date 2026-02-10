import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { detectDdev, getDdevSiteUrl, type DdevConfig } from "../../src/utils/ddev.js";
import * as fs from "node:fs/promises";

vi.mock("node:fs/promises");

describe("detectDdev", () => {
    beforeEach(() => {
        vi.resetAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("returns null when no config file exists", async () => {
        vi.mocked(fs.readFile).mockRejectedValue(new Error("ENOENT"));

        const result = await detectDdev("/project");
        expect(result).toBeNull();
    });

    it("parses basic DDEV config", async () => {
        vi.mocked(fs.readFile).mockResolvedValue(`
name: my-project
type: wordpress
`);

        const result = await detectDdev("/project");
        expect(result).toEqual({
            name: "my-project",
            router_http_port: undefined,
            router_https_port: undefined,
        });
    });

    it("parses DDEV config with custom ports", async () => {
        vi.mocked(fs.readFile).mockResolvedValue(`
name: my-project
router_http_port: "8080"
router_https_port: "8443"
`);

        const result = await detectDdev("/project");
        expect(result).toEqual({
            name: "my-project",
            router_http_port: "8080",
            router_https_port: "8443",
        });
    });

    it("handles quoted values", async () => {
        vi.mocked(fs.readFile).mockResolvedValue(`
name: "my-project"
`);

        const result = await detectDdev("/project");
        expect(result?.name).toBe("my-project");
    });

    it("handles single-quoted values", async () => {
        vi.mocked(fs.readFile).mockResolvedValue(`
name: 'my-project'
`);

        const result = await detectDdev("/project");
        expect(result?.name).toBe("my-project");
    });
});

describe("getDdevSiteUrl", () => {
    it("returns standard DDEV URL", () => {
        const config: DdevConfig = { name: "my-project" };
        expect(getDdevSiteUrl(config)).toBe("https://my-project.ddev.site");
    });

    it("omits port suffix for standard HTTPS port", () => {
        const config: DdevConfig = { name: "my-project", router_https_port: "443" };
        expect(getDdevSiteUrl(config)).toBe("https://my-project.ddev.site");
    });

    it("includes port suffix for non-standard port", () => {
        const config: DdevConfig = { name: "my-project", router_https_port: "8443" };
        expect(getDdevSiteUrl(config)).toBe("https://my-project.ddev.site:8443");
    });
});
