import { writeFile, unlink } from "node:fs/promises";
import { resolve } from "node:path";

/**
 * Write the hot file with the dev server URL.
 * The PHP side reads this file to detect dev mode and inject the Vite client.
 */
export async function writeHotFile(
    themeDir: string,
    hotFileName: string,
    serverUrl: string,
): Promise<void> {
    const hotPath = resolve(themeDir, hotFileName);
    await writeFile(hotPath, serverUrl, "utf-8");
}

/**
 * Remove the hot file when the dev server stops.
 */
export async function removeHotFile(themeDir: string, hotFileName: string): Promise<void> {
    const hotPath = resolve(themeDir, hotFileName);
    try {
        await unlink(hotPath);
    } catch {
        // File may not exist, ignore
    }
}
