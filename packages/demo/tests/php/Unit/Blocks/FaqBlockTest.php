<?php

declare(strict_types=1);

use Demo\Blocks\FaqBlock;
use Demo\Blocks\FaqQuestionBlock;
use Studiometa\Foehn\Attributes\AsBlock;
use Studiometa\Foehn\JsonLd\JsonLd;

function createFaqBlock(): WP_Block
{
    return new WP_Block(['title' => 'Questions'], 'theme/faq', [
        new WP_Block(['question' => 'Are prints available?', 'answer' => 'From any series.'], 'theme/faq-question'),
        new WP_Block(['question' => 'Half written', 'answer' => ''], 'theme/faq-question'),
    ]);
}

describe('FaqBlock and FaqQuestionBlock', function () {
    afterEach(function () {
        JsonLd::flush();
    });

    it('accept each other and nothing else', function () {
        $faq = new ReflectionClass(FaqBlock::class)->getAttributes(AsBlock::class)[0]->newInstance();
        $question = new ReflectionClass(FaqQuestionBlock::class)->getAttributes(AsBlock::class)[0]->newInstance();

        expect($faq->allowedBlocks)->toBe(['theme/faq-question']);
        expect($question->parent)->toBe('theme/faq');
    });

    it('reads its questions from its inner blocks and skips one without an answer', function () {
        $block = createFaqBlock();

        expect(new FaqBlock(createFakeViewEngine())->compose($block->attributes, '', $block))->toBe([
            'title' => 'Questions',
            'items' => [['title' => 'Are prints available?', 'content' => 'From any series.']],
        ]);
    });

    it('adds a FAQPage node of the same questions when it renders', function () {
        $block = createFaqBlock();

        new FaqBlock(createFakeViewEngine())->render($block->attributes, '', $block);

        $nodes = JsonLd::flush();

        expect($nodes)->toHaveCount(1);
        expect($nodes[0]['@type'])->toBe('FAQPage');
        expect($nodes[0]['mainEntity'])->toBe([[
            '@type' => 'Question',
            'name' => 'Are prints available?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'From any series.'],
        ]]);
    });

    it('adds no node without a question', function () {
        new FaqBlock(createFakeViewEngine())->render([], '', new WP_Block([], 'theme/faq'));

        expect(JsonLd::flush())->toBe([]);
    });
});
