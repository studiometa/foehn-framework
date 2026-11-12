import { describe, it, expect, vi, beforeAll, afterAll, beforeEach, afterEach } from "vitest";
import { createServer as createHttpServer, type Server } from "node:http";
import { existsSync } from "node:fs";
import { mkdtemp, mkdir, readFile, rm, writeFile } from "node:fs/promises";
import type { AddressInfo } from "node:net";
import { constants, tmpdir } from "node:os";
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
    // TypeScript with an enum: the served module has other line numbers.
    await writeFile(
        join(root, "theme/assets/js/widget.ts"),
        [
            "interface Options {",
            "    size: number;",
            "}",
            "",
            "enum Mode { A, B }",
            "",
            "export function boom(options: Options): never {",
            "    throw new TypeError(`size ${options.size} ${Mode.B}`);",
            "}",
            "",
        ].join("\n"),
    );

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
    let signalListeners: Record<"SIGINT" | "SIGHUP", Function[]>;

    beforeEach(async () => {
        await rm(join(root, "theme/dist"), { recursive: true, force: true });
        signalListeners = {
            SIGINT: process.listeners("SIGINT"),
            SIGHUP: process.listeners("SIGHUP"),
        };
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
        const port = (server.httpServer!.address() as AddressInfo).port;
        await vi.waitFor(async () =>
            expect(await readFile(hotPath(), "utf-8")).toBe(`http://localhost:${port}`),
        );
    });

    it("removes the hot file when the server closes", async () => {
        await vi.waitFor(() => expect(existsSync(hotPath())).toBe(true));
        await server.close();
        await vi.waitFor(() => expect(existsSync(hotPath())).toBe(false));
    });

    // Vite closes the server on SIGTERM only. Ctrl+C and a closed terminal kill
    // the process, and a hot file left behind points every page at a dead server.
    it.each(["SIGINT", "SIGHUP"] as const)(
        "removes the hot file when %s stops the process",
        async (signal) => {
            await vi.waitFor(() => expect(existsSync(hotPath())).toBe(true));
            // Only the plugin's listeners: the test worker has its own.
            const listeners = process
                .listeners(signal)
                .filter((listener) => !signalListeners[signal].includes(listener));
            expect(listeners).toHaveLength(1);
            const exit = vi.spyOn(process, "exit").mockImplementation((() => {}) as never);
            try {
                listeners[0](signal);
                expect(existsSync(hotPath())).toBe(false);
                expect(exit).toHaveBeenCalledWith(128 + constants.signals[signal]);
            } finally {
                exit.mockRestore();
            }
        },
    );

    it("stops listening for signals when the server closes", async () => {
        await server.close();
        expect(process.listeners("SIGINT")).toEqual(signalListeners.SIGINT);
        expect(process.listeners("SIGHUP")).toEqual(signalListeners.SIGHUP);
    });

    it("writes https when the server uses TLS", async () => {
        await server.close();
        // No certificate: the server listens, which is all the hot file needs.
        server = await createServer({
            root,
            configFile: false,
            logLevel: "silent",
            plugins: [plugin()],
            server: { host: "127.0.0.1", port: 0, https: {} },
        });
        await server.listen();
        const port = (server.httpServer!.address() as AddressInfo).port;
        await vi.waitFor(async () =>
            expect(await readFile(hotPath(), "utf-8")).toBe(`https://localhost:${port}`),
        );
    });

    it.each([
        ["an entry PHP enqueues", "/theme/assets/js/app.js", "from-vite"],
        ["a CSS entry", "/theme/assets/css/app.css", "color: red"],
        ["a CSS module import", "/theme/assets/css/app.css?import", "color: red"],
        ["the Vite client", "/@vite/client", "createHotContext"],
        ["a public file", "/robots.txt", "from-public"],
    ])("serves %s from Vite", async (_label, path, expected) => {
        const body = await (await fetch(`${origin()}${path}`)).text();
        expect(body).not.toContain("from-ddev");
        expect(body).toContain(expected);
    });

    it("lets a page of the DDEV site load the Vite client", async () => {
        const response = await fetch(`${origin()}/@vite/client`, {
            headers: { Origin: upstream.url },
        });
        expect(response.headers.get("access-control-allow-origin")).toBe(upstream.url);
    });

    it("answers the HMR ping from Vite", async () => {
        const response = await fetch(`${origin()}/`, {
            headers: { Accept: "text/x-vite-ping" },
        });
        expect(response.status).toBe(204);
    });

    it("keeps the HMR websocket on Vite", async () => {
        const url = `${origin().replace("http", "ws")}/?token=${server.config.webSocketToken}`;
        const socket = new WebSocket(url, "vite-hmr");
        const message = await new Promise<string>((done, fail) => {
            socket.addEventListener("message", (event) => done(String(event.data)));
            socket.addEventListener("error", () => fail(new Error("websocket failed")));
        });
        socket.close();
        expect(JSON.parse(message)).toEqual({ type: "connected" });
    });

    it("writes a console error of the page to the browser log", async () => {
        await sendFromBrowser({ type: "log", data: { level: "error", message: "Missing price" } });

        expect(await browserLog()).toEqual([
            {
                time: expect.stringMatching(/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/),
                level: "console",
                class: "console.error",
                message: "Missing price",
                file: null,
                line: null,
                stack: [],
            },
        ]);
    });

    it("gives the source line of an uncaught error, through the source map", async () => {
        const served = (await (await fetch(`${origin()}/theme/assets/js/widget.ts`)).text()).split(
            "\n",
        );
        const line = served.findIndex((text) => text.includes("throw")) + 1;
        const column = served[line - 1].indexOf("throw") + 1;
        expect(line).not.toBe(8);

        await sendFromBrowser({
            type: "error",
            data: {
                name: "TypeError",
                message: "size 3 1",
                stack: [
                    "TypeError: size 3 1",
                    `    at boom (${origin()}/theme/assets/js/widget.ts?t=1:${line}:${column})`,
                    `    at ${origin()}/node_modules/.vite/deps/chunk.js?v=1:10:3`,
                ].join("\n"),
            },
        });

        expect(await browserLog()).toEqual([
            expect.objectContaining({
                level: "error",
                class: "TypeError",
                message: "size 3 1",
                file: "theme/assets/js/widget.ts",
                line: 8,
                stack: ["theme/assets/js/widget.ts:8 boom()", "+1 other frame"],
            }),
        ]);
    });

    it.each([
        ["the home page", "/"],
        ["the admin", "/wp/wp-admin/"],
        ["a front-end URL", "/sample-page/?preview=true"],
        ["a missing file", "/theme/assets/js/missing.js"],
    ])("proxies %s to DDEV", async (_label, path) => {
        const body = await (await fetch(`${origin()}${path}`)).text();
        expect(body).toBe(`from-ddev ${path}`);
    });

    /** Send an event over the HMR websocket, as the Vite client in the page does. */
    async function sendFromBrowser(data: unknown): Promise<void> {
        const url = `${origin().replace("http", "ws")}/?token=${server.config.webSocketToken}`;
        const socket = new WebSocket(url, "vite-hmr");
        await new Promise((done) => socket.addEventListener("open", done));
        socket.send(JSON.stringify({ type: "custom", event: "vite:forward-console", data }));
        socket.close();
    }

    /** The entries of the browser log, once there is one. */
    async function browserLog(): Promise<unknown[]> {
        const file = join(root, "theme/dist/browser-errors.jsonl");
        await vi.waitFor(() => expect(existsSync(file)).toBe(true));

        return (await readFile(file, "utf-8"))
            .trim()
            .split("\n")
            .map((line) => JSON.parse(line));
    }

    function origin(): string {
        return `http://127.0.0.1:${(server.httpServer!.address() as AddressInfo).port}`;
    }
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
