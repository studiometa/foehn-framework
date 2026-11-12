import { describe, it, expect, beforeEach, afterEach } from "vitest";
import { existsSync, mkdtempSync, readFileSync, rmSync, statSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import {
    appendBrowserEntry,
    browserEntry,
    logFile,
    parseStack,
    MAX_BYTES,
    type BrowserEntry,
    type FrameResolver,
} from "../../src/utils/browser-log.js";

const now = new Date("2026-11-12T10:00:00.123Z");

/** Every module under `/theme/` is the project's own code, at the line it was served. */
const resolve: FrameResolver = async (url, line) => {
    const path = new URL(url).pathname;
    return path.startsWith("/theme/") ? { file: path.slice(1), line } : null;
};

describe("browserEntry", () => {
    it("gives a console call its level as the class", async () => {
        expect(
            await browserEntry(
                { type: "log", data: { level: "warn", message: "Deprecated option" } },
                resolve,
                now,
            ),
        ).toEqual({
            time: "2026-11-12T10:00:00Z",
            level: "console",
            class: "console.warn",
            message: "Deprecated option",
            file: null,
            line: null,
            stack: [],
        });
    });

    it("gives the own frames of an uncaught error, then the others", async () => {
        const entry = await browserEntry(
            {
                type: "error",
                data: {
                    name: "TypeError",
                    message: "card is null",
                    stack: [
                        "TypeError: card is null",
                        "    at http://localhost:5173/node_modules/.vite/deps/js-toolkit.js?v=1:10:3",
                        "    at Card.mounted (http://localhost:5173/theme/assets/js/components/Card.js:12:5)",
                        "    at http://localhost:5173/theme/assets/js/app.js?t=17:4:1",
                    ].join("\n"),
                },
            },
            resolve,
            now,
        );

        expect(entry).toEqual({
            time: "2026-11-12T10:00:00Z",
            level: "error",
            class: "TypeError",
            message: "card is null",
            file: "theme/assets/js/components/Card.js",
            line: 12,
            stack: [
                "theme/assets/js/components/Card.js:12 Card.mounted()",
                "theme/assets/js/app.js:4",
                "+1 other frame",
            ],
        });
    });

    it("calls an unhandled promise rejection a rejection", async () => {
        const entry = await browserEntry(
            { type: "unhandled-rejection", data: { name: "Error", message: "Failed to fetch" } },
            resolve,
            now,
        );

        expect(entry).toMatchObject({ level: "rejection", file: null, line: null, stack: [] });
    });

    it("keeps 8 own frames", async () => {
        const stack = Array.from(
            { length: 10 },
            (_, index) => `    at f${index} (http://localhost:5173/theme/a.js:${index + 1}:1)`,
        ).join("\n");

        const entry = await browserEntry(
            { type: "error", data: { name: "RangeError", message: "Too deep", stack } },
            resolve,
            now,
        );

        expect(entry.stack).toHaveLength(9);
        expect(entry.stack.at(-1)).toBe("+2 other frames");
    });

    it("cuts a long message", async () => {
        const entry = await browserEntry(
            { type: "log", data: { level: "error", message: "x".repeat(5000) } },
            resolve,
            now,
        );

        expect(entry.message).toHaveLength(2001);
        expect(entry.message.endsWith("…")).toBe(true);
    });
});

describe("parseStack", () => {
    it("reads Firefox and Safari frames", () => {
        expect(
            parseStack(
                "mounted@http://localhost:5173/theme/assets/js/app.js:3:7\n@http://localhost:5173/theme/assets/js/app.js:9:1",
            ),
        ).toEqual([
            {
                fn: "mounted",
                url: "http://localhost:5173/theme/assets/js/app.js",
                line: 3,
                column: 7,
            },
            { fn: "", url: "http://localhost:5173/theme/assets/js/app.js", line: 9, column: 1 },
        ]);
    });

    it("skips lines that are not frames", () => {
        expect(parseStack("Error: boom\n    at <anonymous>")).toEqual([]);
    });
});

describe("appendBrowserEntry", () => {
    let directory: string;

    const entry: BrowserEntry = {
        time: "2026-11-12T10:00:00Z",
        level: "console",
        class: "console.error",
        message: "Missing price",
        file: null,
        line: null,
        stack: [],
    };

    beforeEach(() => {
        directory = join(mkdtempSync(join(tmpdir(), "foehn-browser-log-")), "dist");
    });

    afterEach(() => {
        rmSync(join(directory, ".."), { recursive: true, force: true });
    });

    it("creates the directory and adds one line per entry", () => {
        appendBrowserEntry(directory, entry);
        appendBrowserEntry(directory, { ...entry, message: "Second" });

        const lines = readFileSync(logFile(directory, 0), "utf-8").trim().split("\n");
        expect(lines.map((line) => JSON.parse(line).message)).toEqual(["Missing price", "Second"]);
    });

    it("moves a full file down and deletes the oldest", () => {
        appendBrowserEntry(directory, entry);
        writeFileSync(logFile(directory, 0), "a".repeat(MAX_BYTES - 10));
        writeFileSync(logFile(directory, 1), "older\n");
        writeFileSync(logFile(directory, 2), "oldest\n");

        appendBrowserEntry(directory, { ...entry, message: "Newest" });

        expect(JSON.parse(readFileSync(logFile(directory, 0), "utf-8")).message).toBe("Newest");
        expect(statSync(logFile(directory, 1)).size).toBe(MAX_BYTES - 10);
        expect(readFileSync(logFile(directory, 2), "utf-8")).toBe("older\n");
        expect(existsSync(logFile(directory, 3))).toBe(false);
    });
});
