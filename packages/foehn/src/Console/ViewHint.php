<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console;

/**
 * The Twig file a WordPress template of a new post type is rendered with.
 */
final readonly class ViewHint
{
    /**
     * @param string $template The WordPress template, e.g. 'single-event'
     * @param string $view The view name, e.g. 'pages/single-event'
     * @param string $file The file, relative to the theme, e.g. 'templates/pages/single-event.twig'
     * @param class-string|null $controller The template controller that renders the template
     * @param bool $exists Whether the file is already there
     * @param string $directory Where the theme keeps the views of the same kind, '' for the root
     */
    public function __construct(
        public string $template,
        public string $view,
        public string $file,
        public ?string $controller,
        public bool $exists,
        public string $directory,
    ) {}

    /**
     * What to do about the file, in one line.
     */
    public function note(): string
    {
        if ($this->controller === null) {
            return sprintf(
                'No template controller claims %s. Add one with `wp foehn make:controller %s`, and create the view it renders.',
                $this->template,
                $this->template,
            );
        }

        $where = $this->directory === ''
            ? ''
            : sprintf(', and the theme keeps the views of this kind in %s/', $this->directory);

        return sprintf(
            '%s %s: %s renders the %s template%s.',
            $this->exists ? 'Edit' : 'Create',
            $this->file,
            $this->controller,
            $this->template,
            $where,
        );
    }
}
