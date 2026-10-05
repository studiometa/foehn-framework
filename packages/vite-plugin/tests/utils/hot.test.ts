import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { writeHotFile, removeHotFile } from "../../src/utils/hot.js";
import * as fs from "node:fs/promises";

vi.mock("node:fs/promises");

describe("writeHotFile", () => {
    beforeEach(() => {
        vi.resetAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("writes the server URL to the hot file", async () => {
        vi.mocked(fs.writeFile).mockResolvedValue();

        await writeHotFile("/theme/dist/hot", "http://localhost:5173");

        expect(fs.writeFile).toHaveBeenCalledWith(
            "/theme/dist/hot",
            "http://localhost:5173",
            "utf-8",
        );
    });

    it("creates the directory of the hot file", async () => {
        vi.mocked(fs.writeFile).mockResolvedValue();

        await writeHotFile("/theme/dist/hot", "http://localhost:5173");

        expect(fs.mkdir).toHaveBeenCalledWith("/theme/dist", { recursive: true });
    });
});

describe("removeHotFile", () => {
    beforeEach(() => {
        vi.resetAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it("removes the hot file", async () => {
        vi.mocked(fs.unlink).mockResolvedValue();

        await removeHotFile("/theme/dist/hot");

        expect(fs.unlink).toHaveBeenCalledWith("/theme/dist/hot");
    });

    it("silently ignores missing file", async () => {
        vi.mocked(fs.unlink).mockRejectedValue(new Error("ENOENT"));

        // Should not throw
        await expect(removeHotFile("/theme/dist/hot")).resolves.toBeUndefined();
    });
});
