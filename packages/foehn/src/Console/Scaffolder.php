<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console;

/**
 * The part every `make:` command shares: generate, write or plan, and report.
 *
 * A command only reads its own arguments and describes the file in a generation
 * request. The flags that do not depend on what is generated — `--force`, `--dry-run`
 * and `--format` — are read here, once, so every command takes them the same way and
 * a coding agent can drive any of them without reading its output as prose.
 *
 * Where the file goes follows the project when ProjectConventions is given: next to
 * the classes of the same kind, in their namespace, under their parent class.
 *
 * Nothing here asks a question. A value that is missing is the command's to report
 * as an error, and a file that exists is an error unless `--force` is given, so a run
 * without a terminal ends instead of waiting for an answer.
 */
final readonly class Scaffolder
{
    public const array FORMATS = ['text', 'json'];

    private const int PREVIEW_LINES = 50;

    public function __construct(
        private WpCli $cli,
        private ClassFileGenerator $generator,
        private ?ProjectConventions $conventions = null,
    ) {}

    /**
     * Generate the file a request describes, and write it unless `--dry-run` is given.
     *
     * @param array<string, mixed> $assocArgs The command's named arguments
     * @param string $summary What the command made, e.g. 'Block created'
     * @param list<string> $notes What to do next, one line each
     */
    public function scaffold(GenerationRequest $request, array $assocArgs, string $summary, array $notes = []): void
    {
        $format = $assocArgs['format'] ?? 'text';

        if (!is_string($format) || !in_array($format, self::FORMATS, true)) {
            $this->cli->error(sprintf(
                "Unknown format '%s'. Use %s.",
                is_string($format) ? $format : get_debug_type($format),
                implode(' or ', self::FORMATS),
            ));

            return;
        }

        $force = ($assocArgs['force'] ?? null) !== null;
        $dryRun = ($assocArgs['dry-run'] ?? null) !== null;

        $file = $this->generator->generate($this->conventions?->place($request) ?? $request);
        $status = $dryRun ? $this->generator->plan($file, $force) : $this->generator->write($file, $force);

        if ($format === 'json') {
            $this->cli->line($this->json($file, $status, $dryRun, $notes));
        }

        if ($format === 'text') {
            $this->text($file, $status, $summary, $notes);
        }

        if ($status === GenerationStatus::Exists) {
            $this->cli->error(
                "File already exists: {$this->cli->getRelativePath($file->path)}\n" . 'Use --force to overwrite.',
            );
        }
    }

    /**
     * @param list<string> $notes
     */
    private function json(GeneratedFile $file, GenerationStatus $status, bool $dryRun, array $notes): string
    {
        $entry = ['path' => $file->path, 'status' => $status->value];

        // A dry run reports what it would write; a real run wrote it, and the file is there to read.
        if ($dryRun) {
            $entry['contents'] = $file->contents;
        }

        return (string) json_encode(
            ['dryRun' => $dryRun, 'files' => [$entry], 'notes' => $notes],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @param list<string> $notes
     */
    private function text(GeneratedFile $file, GenerationStatus $status, string $summary, array $notes): void
    {
        if ($status === GenerationStatus::Exists) {
            return;
        }

        if ($status->isDryRun()) {
            $this->preview($file, $status);

            return;
        }

        $this->cli->success("{$summary}: {$this->cli->getRelativePath($file->path)}");

        if ($notes === []) {
            return;
        }

        $this->cli->line('');

        foreach ($notes as $note) {
            $this->cli->log($note);
        }
    }

    /**
     * Show what a generated file would contain, without writing it.
     */
    private function preview(GeneratedFile $file, GenerationStatus $status): void
    {
        $verb = $status === GenerationStatus::WouldOverwrite ? 'Would overwrite:' : 'Would create:';

        $this->cli->line('');
        $this->cli->log($this->cli->colorize("%Y{$verb}%n"));
        $this->cli->log("  → {$this->cli->getRelativePath($file->path)}");
        $this->cli->line('');
        $this->cli->log($this->cli->colorize('%YWith content:%n'));
        $this->cli->line('');

        $lines = explode("\n", $file->contents);

        foreach (array_slice($lines, 0, self::PREVIEW_LINES) as $line) {
            $this->cli->line('  ' . $line);
        }

        if (count($lines) > self::PREVIEW_LINES) {
            $this->cli->line('');
            $this->cli->log($this->cli->colorize(
                '%C  ... (' . (count($lines) - self::PREVIEW_LINES) . ' more lines)%n',
            ));
        }
    }
}
