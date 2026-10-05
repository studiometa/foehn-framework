import { statSync } from "node:fs";
import type { IncomingMessage } from "node:http";
import type { AddressInfo } from "node:net";
import { constants } from "node:os";
import { join, resolve } from "node:path";
import type { Plugin, ResolvedConfig, ViteDevServer } from "vite";
import type { FoehnPluginOptions, ResolvedFoehnPluginOptions } from "./options.js";
import { resolveOptions } from "./options.js";
import {
    resolveGlobPatterns,
    detectDdev,
    getDdevSiteUrl,
    writeHotFile,
    removeHotFile,
} from "./utils/index.js";

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

        async config(_userConfig, { command: _command }) {
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
                server: proxyTarget
                    ? {
                          proxy: {
                              // Vite answers what it can serve, DDEV the rest
                              "/": {
                                  target: proxyTarget,
                                  changeOrigin: true,
                                  secure: false,
                                  bypass: (req) =>
                                      isServedByVite(req, config) ? req.url : undefined,
                              },
                          },
                      }
                    : undefined,
            };
        },

        configResolved(resolvedConfig) {
            config = resolvedConfig;
        },

        configureServer(server: ViteDevServer) {
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
