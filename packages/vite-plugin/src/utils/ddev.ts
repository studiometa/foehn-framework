import { readFile } from "node:fs/promises";
import { resolve } from "node:path";

/**
 * DDEV project configuration structure.
 */
export interface DdevConfig {
    name: string;
    router_http_port?: string;
    router_https_port?: string;
}

/**
 * Detect DDEV configuration from the project root.
 * Returns null if no DDEV config is found.
 */
export async function detectDdev(projectRoot: string): Promise<DdevConfig | null> {
    const configPath = resolve(projectRoot, ".ddev/config.yaml");

    try {
        const content = await readFile(configPath, "utf-8");
        return parseDdevConfig(content);
    } catch {
        return null;
    }
}

/**
 * Parse DDEV YAML config (simple parser, no dependency needed).
 */
function parseDdevConfig(content: string): DdevConfig {
    const lines = content.split("\n");
    const config: Record<string, string> = {};

    for (const line of lines) {
        const match = line.match(/^(\w+):\s*(.+)$/);
        if (match) {
            const key = match[1];
            let value = match[2].trim();
            // Remove quotes if present
            if (
                (value.startsWith('"') && value.endsWith('"')) ||
                (value.startsWith("'") && value.endsWith("'"))
            ) {
                value = value.slice(1, -1);
            }
            config[key] = value;
        }
    }

    return {
        name: config.name ?? "unknown",
        router_http_port: config.router_http_port,
        router_https_port: config.router_https_port,
    };
}

/**
 * Get the DDEV site URL for proxy configuration.
 */
export function getDdevSiteUrl(config: DdevConfig): string {
    const port = config.router_https_port ?? "443";
    const portSuffix = port === "443" ? "" : `:${port}`;
    return `https://${config.name}.ddev.site${portSuffix}`;
}
