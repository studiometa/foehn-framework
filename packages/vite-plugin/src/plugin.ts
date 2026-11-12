import { statSync } from "node:fs";
import type { IncomingMessage } from "node:http";
import type { AddressInfo } from "node:net";
import { constants } from "node:os";
import { isAbsolute, join, relative, resolve } from "node:path";
import { originalPositionFor, TraceMap, type SourceMapInput } from "@jridgewell/trace-mapping";
import type { DevEnvironment, Plugin, ResolvedConfig, ViteDevServer } from "vite";
import type { FoehnPluginOptions, ResolvedFoehnPluginOptions } from "./options.js";
import { resolveOptions } from "./options.js";
import {
    resolveGlobPatterns,
    detectDdev,
    getDdevSiteUrl,
    writeHotFile,
    removeHotFile,
    appendBrowserEntry,
    browserEntry,
    type ForwardConsolePayload,
    type SourcePosition,
} from "./utils/index.js";

/**
 * The origins Vite allows by default (`defaultAllowedOrigins`, exported by Vite
 * 6.0.9 and later only).
 */
const LOCALHOST_ORIGINS = /^https?:\/\/(?:(?:[^:]+\.)?localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$/;

/** Signals that end the process without Vite closing the server first. */
const EXIT_SIGNALS = ["SIGINT", "SIGHUP"] as const;

/**
 * Creates the Føhn Vite plugin.
 */
export function foehn(options: FoehnPluginOptions): Plugin[] {
    const resolvedOptions = resolveOptions(options);
    const outDir = resolve(resolvedOptions.themeDir, resolvedOptions.outDir);
    // Inside the build output, where `ViteManifest` looks for it.
    const hotPath = resolve(outDir, resolvedOptions.hotFile);
    let config: ResolvedConfig;

    const mainPlugin: Plugin = {
        name: "foehn",
        enforce: "pre",

        async config(userConfig) {
            const entries = await resolveGlobPatterns(
                resolvedOptions.input,
                resolvedOptions.themeDir,
            );

            // Detect DDEV for proxy configuration
            const ddevConfig = await detectDdev(resolvedOptions.themeDir);
            const proxyTarget = ddevConfig ? getDdevSiteUrl(ddevConfig) : undefined;

            return {
                build: {
                    manifest: true,
                    outDir,
                    rollupOptions: {
                        input: entries,
                    },
                },
                server: {
                    // Vite 8 turns it on only when it runs inside a coding agent. On,
                    // the page sends its console errors and uncaught errors to the
                    // server, which writes them to the browser log for any agent.
                    forwardConsole: userConfig.server?.forwardConsole ?? true,
                    // A page opened on the DDEV URL loads `/@vite/client` from the dev
                    // server, which Vite allows only for localhost pages by default.
                    cors:
                        userConfig.server?.cors ??
                        (proxyTarget
                            ? { origin: [LOCALHOST_ORIGINS, new URL(proxyTarget).origin] }
                            : undefined),
                    proxy: proxyTarget
                        ? {
                              // Vite answers what it can serve, DDEV the rest
                              "/": {
                                  target: proxyTarget,
                                  changeOrigin: true,
                                  secure: false,
                                  bypass: (req) =>
                                      isServedByVite(req, config) ? req.url : undefined,
                              },
                          }
                        : undefined,
                },
            };
        },

        configResolved(resolvedConfig) {
            config = resolvedConfig;
        },

        configureServer(server: ViteDevServer) {
            // Beside the hot file, where `wp foehn errors --browser` reads it
            const client = server.environments.client;
            client.hot.on("vite:forward-console", (payload: ForwardConsolePayload) => {
                browserEntry(payload, (url, line, column) =>
                    findSource(client, config.root, url, line, column),
                )
                    .then((entry) => appendBrowserEntry(outDir, entry))
                    .catch((error: Error) =>
                        config.logger.warn(
                            `[foehn] Cannot write the browser log: ${error.message}`,
                        ),
                    );
            });

            const httpServer = server.httpServer;
            if (!httpServer) {
                return;
            }

            // Write hot file when server starts, with the address it listens on
            httpServer.once("listening", async () => {
                const address = httpServer.address();
                if (typeof address === "object" && address) {
                    await writeHotFile(hotPath, getServerUrl(address, config));
                }
            });

            // Vite closes the server on SIGTERM only. Ctrl+C (SIGINT) and a
            // closed terminal (SIGHUP) kill the process, and a hot file left
            // behind points every WordPress page at a dead server.
            const onSignal = (signal: NodeJS.Signals) => {
                removeHotFile(hotPath);
                process.exit(128 + constants.signals[signal]);
            };
            for (const signal of EXIT_SIGNALS) {
                process.on(signal, onSignal);
            }

            // Remove hot file when server closes
            httpServer.on("close", () => {
                removeHotFile(hotPath);
                for (const signal of EXIT_SIGNALS) {
                    process.off(signal, onSignal);
                }
            });
        },

        buildEnd() {
            // Ensure hot file is removed after build
            if (config.command === "build") {
                removeHotFile(hotPath);
            }
        },
    };

    // Separate plugin for file watching (full reload)
    const reloadPlugin = createReloadPlugin(resolvedOptions);

    return [mainPlugin, reloadPlugin];
}

/**
 * Creates a plugin that watches files for full reload.
 */
function createReloadPlugin(options: ResolvedFoehnPluginOptions): Plugin {
    return {
        name: "foehn:reload",

        configureServer(server: ViteDevServer) {
            // Watch files for full reload
            for (const pattern of options.reload) {
                const fullPattern = resolve(options.themeDir, pattern);
                server.watcher.add(fullPattern);
            }

            server.watcher.on("change", (file) => {
                // Check if the changed file matches any reload pattern
                const shouldReload = options.reload.some((pattern) => {
                    const fullPattern = resolve(options.themeDir, pattern);
                    return matchPattern(file, fullPattern);
                });

                if (shouldReload) {
                    server.ws.send({ type: "full-reload" });
                }
            });
        },
    };
}

/**
 * Simple pattern matching for file paths.
 */
function matchPattern(file: string, pattern: string): boolean {
    // Convert glob pattern to regex
    const regexPattern = pattern
        .replace(/\*\*/g, "<<<GLOBSTAR>>>")
        .replace(/\*/g, "[^/]*")
        .replace(/<<<GLOBSTAR>>>/g, ".*")
        .replace(/\?/g, ".");

    return new RegExp(`^${regexPattern}$`).test(file);
}

/**
 * Whether the dev server answers a request itself: its internal routes
 * (`/@vite/client`, `/@fs/`, `/@id/`, `/__open-in-editor`), dependencies, the
 * HMR ping, and every file under the root or the public directory, whatever
 * the query (`?import`, `?direct`, `?v=`).
 */
function isServedByVite(req: IncomingMessage, config: ResolvedConfig): boolean {
    if (req.headers.accept === "text/x-vite-ping") {
        return true;
    }

    let path: string;
    try {
        path = decodeURIComponent((req.url ?? "/").split(/[?#]/)[0]);
    } catch {
        // A malformed URL is not a module, let WordPress answer it
        return false;
    }

    if (path.startsWith("/@") || path.startsWith("/__") || path.startsWith("/node_modules/")) {
        return true;
    }

    return [config.root, config.publicDir].some(
        (dir) => dir !== "" && statSync(join(dir, path), { throwIfNoEntry: false })?.isFile(),
    );
}

/**
 * The place in the project's source of a position in a module the browser loaded:
 * the module's file relative to the Vite root, and the line mapped back through
 * the module's source map (TypeScript, JSX and Vue change the line numbers).
 * `null` for a module of a dependency, a URL of another server, or a module the
 * dev server has not served.
 */
async function findSource(
    environment: DevEnvironment,
    root: string,
    url: string,
    line: number,
    column: number,
): Promise<SourcePosition | null> {
    let path: string;
    try {
        // With the query: a Vue block (`?vue&type=script`) is a module of its own.
        // Vite drops the `?t=` of a hot update and the `?import` itself.
        const parsed = new URL(url);
        path = decodeURIComponent(parsed.pathname) + parsed.search;
    } catch {
        return null;
    }

    const module = await environment.moduleGraph.getModuleByUrl(path);
    const file = module?.file ? relative(root, module.file) : "";

    if (file === "" || file.startsWith("..") || isAbsolute(file) || file.includes("node_modules")) {
        return null;
    }

    const map = module?.transformResult?.map;

    if (map && map.mappings !== "") {
        const original = originalPositionFor(new TraceMap(map as SourceMapInput), {
            line,
            column: Math.max(column - 1, 0),
        });

        if (original.line !== null) {
            return { file, line: original.line };
        }
    }

    return { file, line };
}

/**
 * Get the URL a browser reaches the dev server at.
 */
function getServerUrl(address: AddressInfo, config: ResolvedConfig): string {
    const protocol = config.server.https ? "https" : "http";
    const host = ["::", "0.0.0.0", "::1", "127.0.0.1"].includes(address.address)
        ? "localhost"
        : address.family === "IPv6"
          ? `[${address.address}]`
          : address.address;
    return `${protocol}://${host}:${address.port}`;
}
