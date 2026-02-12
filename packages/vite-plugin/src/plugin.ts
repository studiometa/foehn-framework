import { resolve } from "node:path";
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

/**
 * Creates the Føhn Vite plugin.
 */
export function foehn(options: FoehnPluginOptions): Plugin[] {
    const resolvedOptions = resolveOptions(options);
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
                    outDir: resolve(resolvedOptions.themeDir, resolvedOptions.outDir),
                    rollupOptions: {
                        input: entries,
                    },
                },
                server: proxyTarget
                    ? {
                          proxy: {
                              // Proxy all non-asset requests to DDEV
                              "^(?!/@|/node_modules|/src)": {
                                  target: proxyTarget,
                                  changeOrigin: true,
                                  secure: false,
                              },
                          },
                      }
                    : undefined,
            };
        },

        configResolved(resolvedConfig) {
            config = resolvedConfig;
        },

        async configureServer(server: ViteDevServer) {
            const serverUrl = getServerUrl(server, config);

            // Write hot file when server starts
            server.httpServer?.once("listening", async () => {
                const url = serverUrl ?? `http://localhost:${config.server.port}`;
                await writeHotFile(resolvedOptions.themeDir, resolvedOptions.hotFile, url);
            });

            // Remove hot file when server closes
            server.httpServer?.on("close", async () => {
                await removeHotFile(resolvedOptions.themeDir, resolvedOptions.hotFile);
            });
        },

        async buildEnd() {
            // Ensure hot file is removed after build
            if (config.command === "build") {
                await removeHotFile(resolvedOptions.themeDir, resolvedOptions.hotFile);
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
 * Get the dev server URL.
 */
function getServerUrl(server: ViteDevServer, config: ResolvedConfig): string | undefined {
    const address = server.httpServer?.address();
    if (typeof address === "object" && address) {
        const protocol = config.server.https ? "https" : "http";
        const host =
            address.address === "::" || address.address === "0.0.0.0"
                ? "localhost"
                : address.address;
        return `${protocol}://${host}:${address.port}`;
    }
    return undefined;
}
