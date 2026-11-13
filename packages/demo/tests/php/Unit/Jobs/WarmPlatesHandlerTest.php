<?php

declare(strict_types=1);

use Demo\Jobs\WarmPlates;
use Demo\Jobs\WarmPlatesHandler;
use Studiometa\Foehn\Attributes\AsJob;

describe('WarmPlatesHandler', function () {
    it('handles WarmPlates as a job of the demo group', function () {
        $attrs = new ReflectionClass(WarmPlatesHandler::class)->getAttributes(AsJob::class);
        $parameter = new ReflectionMethod(WarmPlatesHandler::class, '__invoke')->getParameters()[0];

        expect($attrs)->toHaveCount(1);
        expect($attrs[0]->newInstance()->group)->toBe('demo');
        expect((string) $parameter->getType())->toBe(WarmPlates::class);
    });
});
