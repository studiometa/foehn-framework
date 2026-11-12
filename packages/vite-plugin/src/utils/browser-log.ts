import { appendFileSync, mkdirSync, renameSync, rmSync, statSync } from "node:fs";
import { join } from "node:path";

/**
 * The browser log: the console errors and the uncaught errors of the pages the dev
 * server serves, one JSON object per line, for `wp foehn errors --browser`.
 *
 * The file has a cap, as the PHP error log does. When the next entry would make it
 * larger than `MAX_BYTES`, it becomes `browser-errors.1.jsonl`, the previous
 * `browser-errors.1.jsonl` becomes `browser-errors.2.jsonl`, and the oldest file is
 * deleted. So the log never holds more than `MAX_FILES` files of `MAX_BYTES`.
 */
export const BROWSER_LOG = "browser-errors";

export const MAX_BYTES = 256 * 1024;

export const MAX_FILES = 3;

/** The characters of a message the entry keeps. */
const MAX_MESSAGE = 2000;

/** The frames of the project's own code the entry keeps. */
const MAX_OWN_FRAMES = 8;

/**
 * What the Vite client sends on `vite:forward-console` (Vite 8 `server.forwardConsole`).
 */
export type ForwardConsolePayload =
    | {
          type: "error" | "unhandled-rejection";
          data: { name: string; message: string; stack?: string };
      }
    | { type: "log"; data: { level: string; message: string } };

/**
 * A place in a source file of the project, relative to the Vite root.
 */
export interface SourcePosition {
    file: string;
    line: number;
}

/**
 * Finds the source file and line of a position in a module the browser loaded, or
 * `null` when the module is not the project's own code (a dependency, another
 * origin, an unknown URL).
 */
export type FrameResolver = (
    url: string,
    line: number,
    column: number,
) => Promise<SourcePosition | null>;

/**
 * One entry of the browser log. The fields are the ones `wp foehn errors` prints.
 */
export interface BrowserEntry {
    time: string;
    level: "error" | "rejection" | "console";
    class: string;
    message: string;
    file: string | null;
    line: number | null;
    stack: string[];
}

/**
 * The entry of one forwarded event.
 *
 * An uncaught error or an unhandled rejection gives the frames of the project's own
 * code, mapped back to the source, then the number of other frames. A console call
 * has no stack.
 */
export async function browserEntry(
    payload: ForwardConsolePayload,
    resolve: FrameResolver,
    now: Date = new Date(),
): Promise<BrowserEntry> {
    const time = now.toISOString().replace(/\.\d+Z$/, "Z");

    if (payload.type === "log") {
        return {
            time,
            level: "console",
            class: `console.${payload.data.level}`,
            message: shorten(payload.data.message),
            file: null,
            line: null,
            stack: [],
        };
    }

    const own: string[] = [];
    let first: SourcePosition | null = null;
    let others = 0;

    for (const frame of parseStack(payload.data.stack ?? "")) {
        const position =
            own.length < MAX_OWN_FRAMES ? await resolve(frame.url, frame.line, frame.column) : null;

        if (position === null) {
            others++;
            continue;
        }

        first ??= position;
        own.push(`${position.file}:${position.line}${frame.fn === "" ? "" : ` ${frame.fn}()`}`);
    }

    if (others > 0) {
        own.push(`+${others} other ${others === 1 ? "frame" : "frames"}`);
    }

    return {
        time,
        level: payload.type === "error" ? "error" : "rejection",
        class: shorten(payload.data.name),
        message: shorten(payload.data.message),
        file: first?.file ?? null,
        line: first?.line ?? null,
        stack: own,
    };
}

/**
 * The frames of a stack, as Chromium (`at fn (url:line:column)`) and Firefox or
 * Safari (`fn@url:line:column`) write them. Other lines, such as the message, are
 * skipped.
 */
export function parseStack(
    stack: string,
): Array<{ fn: string; url: string; line: number; column: number }> {
    const frames = [];

    for (const text of stack.split("\n")) {
        const match =
            /^\s*at\s+(?:(.*?)\s+\()?(.+?):(\d+):(\d+)\)?\s*$/.exec(text) ??
            /^\s*(.*?)@(.+?):(\d+):(\d+)\s*$/.exec(text);

        if (match) {
            frames.push({
                fn: match[1] ?? "",
                url: match[2],
                line: Number(match[3]),
                column: Number(match[4]),
            });
        }
    }

    return frames;
}

/**
 * Add an entry at the end of the log in `directory`. Synchronous, so two events
 * never rotate the files at the same time.
 */
export function appendBrowserEntry(directory: string, entry: BrowserEntry): void {
    const line = `${JSON.stringify(entry)}\n`;
    const file = logFile(directory, 0);

    mkdirSync(directory, { recursive: true });

    const size = statSync(file, { throwIfNoEntry: false })?.size ?? 0;

    if (size > 0 && size + Buffer.byteLength(line) > MAX_BYTES) {
        rmSync(logFile(directory, MAX_FILES - 1), { force: true });

        for (let index = MAX_FILES - 2; index >= 0; index--) {
            if (statSync(logFile(directory, index), { throwIfNoEntry: false })) {
                renameSync(logFile(directory, index), logFile(directory, index + 1));
            }
        }
    }

    appendFileSync(file, line, "utf-8");
}

/**
 * The path of one file of the log: `browser-errors.jsonl`, then
 * `browser-errors.1.jsonl` and `browser-errors.2.jsonl`.
 */
export function logFile(directory: string, index: number): string {
    return join(directory, `${BROWSER_LOG}${index === 0 ? "" : `.${index}`}.jsonl`);
}

function shorten(text: string): string {
    return text.length > MAX_MESSAGE ? `${text.slice(0, MAX_MESSAGE)}…` : text;
}
