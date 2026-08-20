<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Test Case
 |--------------------------------------------------------------------------
 |
 | The closure you provide to your test functions is always bound to a specific
 | PHPUnit test case class. By default, that class is "PHPUnit\Framework\TestCase".
 | You can change this by using the "uses()" function to bind a different class.
 |
 */

// uses(Tests\TestCase::class)->in('Feature');

/*
 |--------------------------------------------------------------------------
 | Expectations
 |--------------------------------------------------------------------------
 |
 | When you're writing tests, you often need to check that values meet certain
 | conditions. Pest provides a set of expectations that allow you to verify
 | that a given value matches a specific condition.
 |
 */

// Named toBeReadonlyClass rather than toBeReadonly: pest-plugin-arch v5 registers
// an expectation under the latter, it wins the name, and its implementation throws
// "Typed property ObjectDescriptionBase::$path must not be accessed before
// initialization" against PHPUnit 13 — so every class asserted readonly failed for a
// reason that had nothing to do with the class.
expect()->extend('toBeReadonlyClass', function () {
    $reflection = new ReflectionClass($this->value);

    return $this->and($reflection->isReadonly())->toBeTrue();
});

require_once __DIR__ . '/helpers.php';
