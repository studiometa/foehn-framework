<?php

declare(strict_types=1);

use Demo\Blocks\FactBlock;
use Demo\Blocks\FactsBlock;
use Studiometa\Foehn\Attributes\AsBlock;

describe('FactsBlock and FactBlock', function () {
    it('accept each other and nothing else', function () {
        $facts = new ReflectionClass(FactsBlock::class)->getAttributes(AsBlock::class)[0]->newInstance();
        $fact = new ReflectionClass(FactBlock::class)->getAttributes(AsBlock::class)[0]->newInstance();

        expect($facts->allowedBlocks)->toBe(['theme/fact']);
        expect($fact->parent)->toBe('theme/facts');
    });

    it('pass a fact its label and value', function () {
        expect(new FactBlock(createFakeViewEngine())->compose(
            ['label' => 'Film', 'value' => 'HP5+'],
            '',
            new WP_Block([]),
        ))->toBe(['label' => 'Film', 'value' => 'HP5+']);
    });
});
