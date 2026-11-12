<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Skills;

use Studiometa\Foehn\Attributes\AsCliCommand;
use Studiometa\Foehn\Console\CliCommandInterface;
use Studiometa\Foehn\Console\WpCli;

#[AsCliCommand(name: 'skill', description: 'Print the Agent Skills of the installed packages', longDescription: <<<'DOC'
    ## DESCRIPTION

    Lists and prints the Agent Skills that the installed packages ship, at the
    versions the project has installed. A coding agent reads them before it writes
    code, so it learns the attributes, conventions and commands of these versions.

    The skills come from every Composer package with a `skills/<name>/SKILL.md`
    file, and from `node_modules/@studiometa/foehn-vite-plugin/skills` when the
    Vite plugin is installed.

    With no name, the command lists the skills. With a name, it prints that
    SKILL.md. The command only reads files.

    ## OPTIONS

    [<name>]
    : The skill to print, for example foehn.

    [--reference=<file>]
    : Print this reference file of the skill instead of SKILL.md, for example
      blocks or references/blocks.md.

    [--list-references]
    : List the reference files of the skill.

    [--format=<format>]
    : Output format of a list. table (default) or json.

    ## EXAMPLES

        # The skills of the installed packages
        wp foehn skill

        # The main instructions of one skill
        wp foehn skill foehn

        # Its reference files, then one of them
        wp foehn skill foehn --list-references
        wp foehn skill foehn --reference=blocks
    DOC)]
final readonly class SkillCommand implements CliCommandInterface
{
    /** @var list<string> */
    private const array FORMATS = ['table', 'json'];

    public function __construct(
        private WpCli $cli,
        private SkillCatalog $catalog,
    ) {}

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $format = $assocArgs['format'] ?? 'table';

        if (!is_string($format) || !in_array($format, self::FORMATS, true)) {
            $this->cli->error(sprintf("Unknown format '%s'. Use table or json.", is_string($format) ? $format : ''));

            return;
        }

        $name = $args[0] ?? null;

        if ($name === null) {
            $this->listSkills($format);

            return;
        }

        $skill = $this->catalog->find($name);

        if ($skill === null) {
            $this->cli->error(sprintf("Unknown skill '%s'. %s", $name, $this->available()));

            return;
        }

        if (array_key_exists('list-references', $assocArgs)) {
            $this->listReferences($skill, $format);

            return;
        }

        $reference = $assocArgs['reference'] ?? null;

        if ($reference === null) {
            $this->cli->line($skill->contents());

            return;
        }

        $contents = is_string($reference) ? $skill->reference($reference) : null;

        if ($contents === null) {
            $references = $skill->references();

            $this->cli->error(sprintf(
                "Unknown reference '%s' in skill '%s'. %s",
                is_string($reference) ? $reference : '',
                $skill->name,
                $references === []
                    ? 'This skill has no reference files.'
                    : 'Available references: ' . implode(', ', $references) . '.',
            ));

            return;
        }

        $this->cli->line($contents);
    }

    private function listSkills(string $format): void
    {
        $skills = $this->catalog->all();

        if ($format === 'json') {
            $this->json(array_map(static fn(Skill $skill): array => $skill->toArray(), $skills));

            return;
        }

        if ($skills === []) {
            $this->cli->log('No installed package ships a skill.');

            return;
        }

        $this->table(
            array_map(static fn(Skill $skill): array => [
                $skill->name,
                $skill->package,
                $skill->version ?? '-',
                $skill->summary(100),
            ], $skills),
            ['name', 'package', 'version', 'description'],
        );
        $this->cli->line('');
        $this->cli->log('Print one with: wp foehn skill <name>');
    }

    private function listReferences(Skill $skill, string $format): void
    {
        $references = $skill->references();

        if ($format === 'json') {
            $this->json(array_map(static fn(string $file): array => [
                'reference' => $file,
                'path' => $skill->path . '/references/' . $file,
            ], $references));

            return;
        }

        if ($references === []) {
            $this->cli->log(sprintf("The skill '%s' has no reference files.", $skill->name));

            return;
        }

        foreach ($references as $file) {
            $this->cli->log($file);
        }
    }

    private function available(): string
    {
        $names = array_values(array_unique(array_map(
            static fn(Skill $skill): string => $skill->name,
            $this->catalog->all(),
        )));

        return $names === []
            ? 'No installed package ships a skill.'
            : 'Available skills: ' . implode(', ', $names) . '.';
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function json(array $rows): void
    {
        $this->cli->line((string) json_encode(
            $rows,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_PARTIAL_OUTPUT_ON_ERROR,
        ));
    }

    /**
     * Padded rows. The last column is not padded, so a long description does not
     * push the others.
     *
     * @param list<list<string>> $rows
     * @param list<string> $headers
     */
    private function table(array $rows, array $headers): void
    {
        $lines = [$headers, ...$rows];
        $widths = [];

        foreach ($lines as $line) {
            foreach ($line as $index => $value) {
                $widths[$index] = max($widths[$index] ?? 0, mb_strlen($value));
            }
        }

        $last = count($headers) - 1;

        foreach ($lines as $line) {
            $cells = [];

            foreach ($line as $index => $value) {
                $cells[] = $index === $last ? $value : $value . str_repeat(' ', $widths[$index] - mb_strlen($value));
            }

            $this->cli->log('  ' . rtrim(implode('  ', $cells)));
        }
    }
}
