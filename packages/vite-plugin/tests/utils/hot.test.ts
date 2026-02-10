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

        await writeHotFile("/theme", "hot", "http://localhost:5173");

        expect(fs.writeFile).toHaveBeenCalledWith("/theme/hot", "http://localhost:5173", "utf-8");
    });

    it("uses custom hot file name", async () => {
        vi.mocked(fs.writeFile).mockResolvedValue();

        await writeHotFile("/theme", ".hot", "http://localhost:5173");

        expect(fs.writeFile).toHaveBeenCalledWith("/theme/.hot", "http://localhost:5173", "utf-8");
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

        await removeHotFile("/theme", "hot");

        expect(fs.unlink).toHaveBeenCalledWith("/theme/hot");
    });

    it("silently ignores missing file", async () => {
        vi.mocked(fs.unlink).mockRejectedValue(new Error("ENOENT"));

        // Should not throw
        await expect(removeHotFile("/theme", "hot")).resolves.toBeUndefined();
    });
});
