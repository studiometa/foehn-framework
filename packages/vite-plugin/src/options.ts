/**
 * Options for the Føhn Vite plugin.
 */
export interface FoehnPluginOptions {
    /**
     * Glob patterns for entry points.
     * @example ["src/js/app.js", "src/css/app.css"]
     */
    input: string | string[];

    /**
     * Files to watch for full reload.
     * @default ["templates/**\/*.twig"]
     */
    reload?: string | string[];

    /**
     * Output directory for built assets.
     * @default "dist"
     */
    outDir?: string;

    /**
     * Theme directory context.
     * @default process.cwd()
     */
    themeDir?: string;

    /**
     * Name of the hot file generated during development.
     * @default "hot"
     */
    hotFile?: string;
}

/**
 * Resolved options with all defaults applied.
 */
export interface ResolvedFoehnPluginOptions {
    input: string[];
    reload: string[];
    outDir: string;
    themeDir: string;
    hotFile: string;
}

/**
 * Default values for optional options.
 */
export const defaultOptions: Omit<ResolvedFoehnPluginOptions, "input"> = {
    reload: ["templates/**/*.twig"],
    outDir: "dist",
    themeDir: process.cwd(),
    hotFile: "hot",
};

/**
 * Normalize and resolve plugin options.
 */
export function resolveOptions(options: FoehnPluginOptions): ResolvedFoehnPluginOptions {
    const input = Array.isArray(options.input) ? options.input : [options.input];

    const reload = options.reload
        ? Array.isArray(options.reload)
            ? options.reload
            : [options.reload]
        : defaultOptions.reload;

    return {
        input,
        reload,
        outDir: options.outDir ?? defaultOptions.outDir,
        themeDir: options.themeDir ?? defaultOptions.themeDir,
        hotFile: options.hotFile ?? defaultOptions.hotFile,
    };
}
