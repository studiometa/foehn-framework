<?php

declare(strict_types=1);

use Demo\Blocks\RelatedProjectsBlock;

describe('RelatedProjectsBlock', function () {
    it('picks projects with a posts control', function () {
        $projectIds = RelatedProjectsBlock::attributes()['projectIds'];

        expect($projectIds['control'])->toBe('posts');
        expect($projectIds['postTypes'])->toBe(['project']);
        expect($projectIds['type'])->toBe('array');
    });

    it('runs no query when no project is picked', function () {
        $context = new RelatedProjectsBlock(createFakeViewEngine())->compose(
            ['projectIds' => [], 'title' => 'See also'],
            '',
            new WP_Block([]),
        );

        expect($context)->toBe(['title' => 'See also', 'projects' => []]);
    });
});
