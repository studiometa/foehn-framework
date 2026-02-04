<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks\Cleanup;

use Studiometa\Foehn\Attributes\AsFilter;

/**
 * Clean up WordPress content output.
 *
 * - Removes empty `<p>&nbsp;</p>` paragraphs often left by the visual editor
 * - Strips the type prefix from archive titles ("Category:", "Tag:", etc.)
 */
final class CleanContent
{
    /**
     * Remove empty paragraphs from content.
     */
    #[AsFilter('the_content', priority: 20)]
    public function cleanEmptyParagraphs(string $content): string
    {
        return (string) preg_replace('/<p>(\s|&nbsp;)*<\/p>/', '', $content);
    }

    /**
     * Remove archive title prefix (e.g. "Category:", "Tag:", "Archives:").
     */
    #[AsFilter('get_the_archive_title')]
    public function cleanArchiveTitlePrefix(string $title): string
    {
        return (string) preg_replace('/^[^:]+:\s*/', '', $title);
    }
}
