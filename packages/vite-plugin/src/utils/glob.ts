import { glob } from "fast-glob";
import { resolve, relative } from "node:path";

/**
 * Resolve glob patterns to absolute file paths.
 */
export async function resolveGlobPatterns(
    patterns: string[],
    cwd: string,
): Promise<Record<string, string>> {
    const entries: Record<string, string> = {};

    for (const pattern of patterns) {
        // Check if pattern contains glob characters
        if (pattern.includes("*") || pattern.includes("?") || pattern.includes("{")) {
            const files = await glob(pattern, { cwd, absolute: true });
            for (const file of files) {
                const relativePath = relative(cwd, file);
                // Remove extension for entry name
                const name = relativePath.replace(/\.[^/.]+$/, "");
                entries[name] = file;
            }
        } else {
            // Direct file path
            const absolutePath = resolve(cwd, pattern);
            const name = pattern.replace(/\.[^/.]+$/, "");
            entries[name] = absolutePath;
        }
    }

    return entries;
}
