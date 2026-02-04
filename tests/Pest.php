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

expect()->extend('toBeReadonly', function () {
    $reflection = new ReflectionClass($this->value);

    return $this->and($reflection->isReadonly())->toBeTrue();
});

/*
 |--------------------------------------------------------------------------
 | Functions
 |--------------------------------------------------------------------------
 |
 | While Pest is very powerful out-of-the-box, you may have some testing code
 | specific to your project that you don't want to repeat in every file.
 | Here you can define functions that can be used in all your test files.
 |
 */
