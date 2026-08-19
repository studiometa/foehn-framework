<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ViewEngineInterface;

/*
 |--------------------------------------------------------------------------
 | Pest Configuration for Foehn Starter
 |--------------------------------------------------------------------------
 */

/**
 * Create a fake ViewEngineInterface for testing.
 */
function createFakeViewEngine(?Closure $renderCallback = null): ViewEngineInterface
{
    return new class($renderCallback) implements ViewEngineInterface {
        private array $shared = [];

        public function __construct(
            private readonly ?Closure $renderCallback = null,
        ) {}

        public function render(string $template, array|object $context = []): string
        {
            if ($this->renderCallback) {
                return ($this->renderCallback)($template, $context);
            }

            return '';
        }

        public function renderFirst(array $templates, array|object $context = []): string
        {
            return $this->render($templates[0] ?? '', $context);
        }

        public function exists(string $template): bool
        {
            return true;
        }

        public function share(string $key, mixed $value): void
        {
            $this->shared[$key] = $value;
        }

        public function getShared(): array
        {
            return $this->shared;
        }
    };
}
