import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { writeHotFile, removeHotFile } from "../../src/utils/hot.js";
import * as fs from "node:fs/promises";
import { rmSync } from "node:fs";

vi.mock("node:fs/promises");
vi.mock("node:fs");

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

    it("removes the hot file, missing or not", () => {
        removeHotFile("/theme/dist/hot");

        expect(rmSync).toHaveBeenCalledWith("/theme/dist/hot", { force: true });
    });
});
