import { describe, it, expect, vi, beforeAll, afterAll, beforeEach, afterEach } from "vitest";
import { createServer as createHttpServer, type Server } from "node:http";
import { existsSync } from "node:fs";
import { mkdtemp, mkdir, readFile, rm, writeFile } from "node:fs/promises";
import type { AddressInfo } from "node:net";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { build, createServer, type ViteDevServer } from "vite";
import { foehn } from "../src/plugin.js";

// The DDEV site URL cannot point at a local server, so the tests swap it for
// the fake upstream below.
const upstream = vi.hoisted(() => ({ url: "" }));

vi.mock("../src/utils/ddev.js", async (importOriginal) => ({
    ...(await importOriginal<typeof import("../src/utils/ddev.js")>()),
    getDdevSiteUrl: () => upstream.url,
}));

let root: string;
let ddevServer: Server;

beforeAll(async () => {
    root = await mkdtemp(join(tmpdir(), "foehn-vite-plugin-"));
    await mkdir(join(root, ".ddev"), { recursive: true });
    await mkdir(join(root, "theme/assets/js"), { recursive: true });
    await mkdir(join(root, "theme/assets/css"), { recursive: true });
    await mkdir(join(root, "public"), { recursive: true });
    await writeFile(join(root, ".ddev/config.yaml"), "name: fixture\n");
    await writeFile(join(root, "theme/assets/js/app.js"), "export const entry = 'from-vite';\n");
    await writeFile(join(root, "theme/assets/css/app.css"), "body { color: red; }\n");
    await writeFile(join(root, "public/robots.txt"), "from-public\n");

    ddevServer = createHttpServer((req, res) => {
        res.writeHead(200, { "Content-Type": "text/plain" });
        res.end(`from-ddev ${req.url}`);
    });
    await new Promise<void>((done) => ddevServer.listen(0, "127.0.0.1", done));
    upstream.url = `http://127.0.0.1:${(ddevServer.address() as AddressInfo).port}`;
});

afterAll(async () => {
    await new Promise((done) => ddevServer.close(done));
    await rm(root, { recursive: true, force: true });
});

function plugin() {
    return foehn({
        input: ["theme/assets/js/app.js", "theme/assets/css/app.css"],
        outDir: "theme/dist",
        themeDir: root,
    });
}

const hotPath = () => join(root, "theme/dist/hot");

describe("dev server", () => {
    let server: ViteDevServer;

    beforeEach(async () => {
        await rm(join(root, "theme/dist"), { recursive: true, force: true });
        server = await createServer({
            root,
            configFile: false,
            logLevel: "silent",
            plugins: [plugin()],
            server: { host: "127.0.0.1", port: 0 },
        });
        await server.listen();
    });

    afterEach(async () => {
        await server.close();
    });

    it("writes the hot file inside outDir, where ViteManifest reads it", async () => {
        await vi.waitFor(() => expect(existsSync(hotPath())).toBe(true));
        expect(existsSync(join(root, "hot"))).toBe(false);
    });

    it("writes the URL the server listens on", async () => {
        await vi.waitFor(() => expect(existsSync(hotPath())).toBe(true));
        const port = (server.httpServer!.address() as AddressInfo).port;
        expect(await readFile(hotPath(), "utf-8")).toBe(`http://localhost:${port}`);
    });

    it("removes the hot file when the server closes", async () => {
        await vi.waitFor(() => expect(existsSync(hotPath())).toBe(true));
        await server.close();
        await vi.waitFor(() => expect(existsSync(hotPath())).toBe(false));
    });
});

describe("build", () => {
    it("removes the hot file", async () => {
        await mkdir(join(root, "theme/dist"), { recursive: true });
        await writeFile(hotPath(), "http://localhost:5173");

        await build({
            root,
            configFile: false,
            logLevel: "silent",
            plugins: [plugin()],
            build: { emptyOutDir: false },
        });

        expect(existsSync(hotPath())).toBe(false);
    });
});
