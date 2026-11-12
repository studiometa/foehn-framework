<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Skills;

/**
 * The Føhn block of a project's AGENTS.md: a short directive and an index of the
 * installed skills and their references.
 *
 * Agents read AGENTS.md on every task but load a skill only when they decide to, and
 * they often do not. So the few rules that matter most, and where to read the rest,
 * go in AGENTS.md. The index is built from the installed packages, so it follows
 * `composer update` when `wp foehn agents-md` runs again.
 *
 * The block sits between two markers. merge() replaces what is between them and
 * leaves every other byte of the file as it was.
 */
final readonly class AgentsBlock
{
    public const string BEGIN = '<!-- foehn:begin -->';

    public const string END = '<!-- foehn:end -->';

    /** Characters of a summary in the index, so a line stays readable. */
    private const int SUMMARY_LENGTH = 200;

    private const string DIRECTIVE = <<<'MD'
        ## Føhn

        The skills below describe the Føhn versions this project has installed. Trust them over what you remember of Føhn, and read the skill and the reference of an area before you write code in it. Run `wp` the way this project runs WP-CLI, for example `ddev wp` in DDEV.

        - Before you add a class, read what the project declares: `wp foehn inspect --format=json`, then the sections you need in full, for example `wp foehn inspect post-types templates views --format=json`.
        - Create classes with the generators: `wp foehn make:<type> <name> --dry-run --format=json` first, then the same command without `--dry-run`.
        - After you add a class or change an attribute, run `wp foehn discovery:clear`, then check that `problems` in `wp foehn inspect --format=json` is empty.
        - Put sample content and menus in seeders (`wp foehn make:seeder`, then `wp foehn seed`), never only in the database.
        - When a page fails, read `wp foehn errors` (the last PHP error, with its template, controller and view), `wp foehn errors --browser` (the browser console, while `npm run dev` runs) and `wp foehn log` (the debug log, grouped).
        - When a page renders the wrong template, view or data, run `wp foehn trace <path>`.
        MD;

    public function __construct(
        private SkillCatalog $catalog,
    ) {}

    /**
     * The block, markers included, without a final line break.
     */
    public function render(): string
    {
        $lines = [
            self::BEGIN,
            '<!-- Written by `wp foehn agents-md` from the installed packages. Run it again after `composer update` or `npm update`: it replaces everything between these markers. -->',
            '',
            self::DIRECTIVE,
            '',
            '### Skills',
            '',
        ];

        // Markdown formatters put a blank line between a list and the HTML that
        // follows it. Writing it here keeps a formatted file up to date.
        $end = ['', self::END];
        $skills = $this->catalog->all();

        if ($skills === []) {
            return implode("\n", [...$lines, 'No installed package ships a skill.', ...$end]);
        }

        $lines[] = 'Each line gives the command that prints the file. When WordPress cannot boot, read the files directly: a skill is the `SKILL.md` in parentheses, and its reference `<file>` is `references/<file>.md` next to it.';
        $lines[] = '';

        foreach ($skills as $skill) {
            array_push($lines, ...self::index($skill));
        }

        return implode("\n", [...$lines, ...$end]);
    }

    /**
     * The file with the block put in: between the markers when the file has them, at
     * the end when it does not, alone when there is no file.
     *
     * @param ?string $contents The file, or null when it does not exist
     *
     * @throws \InvalidArgumentException When the markers are not one begin before one end
     */
    public static function merge(?string $contents, string $block): string
    {
        if ($contents === null || $contents === '') {
            return $block . "\n";
        }

        $begins = substr_count($contents, self::BEGIN);
        $ends = substr_count($contents, self::END);

        if ($begins === 0 && $ends === 0) {
            return $contents . (str_ends_with($contents, "\n") ? "\n" : "\n\n") . $block . "\n";
        }

        $start = strpos($contents, self::BEGIN);
        $stop = strpos($contents, self::END);

        if ($begins !== 1 || $ends !== 1 || $start === false || $stop === false || $stop < $start) {
            throw new \InvalidArgumentException(sprintf(
                'It must hold %s once, then %s once, and holds %d and %d.',
                self::BEGIN,
                self::END,
                $begins,
                $ends,
            ));
        }

        return substr($contents, 0, $start) . $block . substr($contents, $stop + strlen(self::END));
    }

    /**
     * Whether the file already holds a block.
     */
    public static function isIn(string $contents): bool
    {
        return str_contains($contents, self::BEGIN);
    }

    /**
     * One line for the skill, then one indented line for each reference.
     *
     * @return list<string>
     */
    private static function index(Skill $skill): array
    {
        $lines = [self::line(
            sprintf('`wp foehn skill %s` (`%s/SKILL.md`)', $skill->name, $skill->path),
            $skill->summary(self::SUMMARY_LENGTH),
        )];

        foreach ($skill->references() as $file) {
            $name = str_ends_with($file, '.md') ? substr($file, 0, -3) : $file;

            $lines[] =
                '  '
                . self::line(
                    sprintf('`wp foehn skill %s --reference=%s`', $skill->name, $name),
                    $skill->referenceSummary($file, self::SUMMARY_LENGTH),
                );
        }

        return $lines;
    }

    private static function line(string $command, string $summary): string
    {
        return '- ' . $command . ($summary === '' ? '' : ': ' . $summary);
    }
}
