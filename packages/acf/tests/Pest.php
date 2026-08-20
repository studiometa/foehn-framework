<?php

declare(strict_types=1);

// Named toBeReadonlyClass rather than toBeReadonly: pest-plugin-arch v5 registers
// an expectation under the latter, it wins the name, and its implementation throws
// "Typed property ObjectDescriptionBase::$path must not be accessed before
// initialization" against PHPUnit 13 — so every class asserted readonly failed for a
// reason that had nothing to do with the class.
expect()->extend('toBeReadonlyClass', function () {
    $reflection = new ReflectionClass($this->value);

    return $this->and($reflection->isReadonly())->toBeTrue();
});

// The discovery helpers are the framework's, and private to the monorepo. This
// package reads them from there rather than keeping a copy that would drift.
require_once dirname(__DIR__, 2) . '/foehn/tests/helpers.php';
