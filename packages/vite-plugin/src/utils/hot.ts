import { rmSync } from "node:fs";
import { mkdir, writeFile } from "node:fs/promises";
import { dirname } from "node:path";

/**
 * Write the hot file with the dev server URL.
 * The PHP side reads this file to detect dev mode and inject the Vite client.
 * The directory is created when it does not exist yet, so the dev server works
 * before the first build.
 */
export async function writeHotFile(hotPath: string, serverUrl: string): Promise<void> {
    await mkdir(dirname(hotPath), { recursive: true });
    await writeFile(hotPath, serverUrl, "utf-8");
}

/**
 * Remove the hot file when the dev server stops. Synchronous, so it is done
 * before a process that is exiting goes away. A missing file is not an error.
 */
export function removeHotFile(hotPath: string): void {
    rmSync(hotPath, { force: true });
}
