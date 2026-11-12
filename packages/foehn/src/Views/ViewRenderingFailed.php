<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views;

use RuntimeException;
use Twig\Error\Error as TwigError;

/**
 * A view that could not be rendered, with what was asked for and where it failed.
 *
 * The view a controller asks for is rarely the template that fails: `pages/single`
 * includes a partial, the partial reads a variable, and Twig's own message names
 * only the partial. This message names both, keeps Twig's message and line, and
 * keeps the Twig error as the previous exception.
 */
final class ViewRenderingFailed extends RuntimeException
{
    /**
     * None of the views exists in any directory Timber searches.
     *
     * @param array<string, string> $files The file of each view, by view name, in the order they were tried
     * @param list<string> $directories The directories Timber searched, in order
     * @param string|null $templates The directory the project keeps its templates in, where the fix creates the file
     */
    public static function notFound(array $files, array $directories, ?string $templates): self
    {
        $views = array_map(static fn(string $view): string => "'{$view}'", array_keys($files));

        $message = sprintf(
            'No template found for the %s %s. Searched %s in: %s.',
            count($views) === 1 ? 'view' : 'views',
            implode(', ', $views),
            implode(', ', $files),
            $directories === [] ? 'no directory' : implode(', ', $directories),
        );

        if ($templates === null || $files === []) {
            return new self($message);
        }

        return new self(
            $message
            . (
                count($files) === 1
                    ? sprintf(' Fix: create %s/%s.', $templates, reset($files))
                    : sprintf(' Fix: create one of %s in %s.', implode(', ', $files), $templates)
            ),
        );
    }

    /**
     * Twig failed while rendering a view or a template it includes.
     */
    public static function inTwig(string $view, TwigError $error): self
    {
        $template = $error->getSourceContext()?->getName();
        $line = $error->getTemplateLine();

        $where = match (true) {
            $template === null => '',
            $line > 0 => sprintf(' in %s at line %d', $template, $line),
            default => sprintf(' in %s', $template),
        };

        return new self(
            sprintf("Rendering the view '%s' failed%s: %s", $view, $where, $error->getRawMessage()),
            0,
            $error,
        );
    }
}
