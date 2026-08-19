<?php

declare(strict_types=1);

expect()->extend('toBeReadonly', function () {
    $reflection = new ReflectionClass($this->value);

    return $this->and($reflection->isReadonly())->toBeTrue();
});

// The discovery helpers are the framework's, and private to the monorepo. This
// package reads them from there rather than keeping a copy that would drift.
require_once dirname(__DIR__, 2) . '/foehn/tests/helpers.php';
