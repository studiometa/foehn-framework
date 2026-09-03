<?php

declare(strict_types=1);

use Studiometa\Foehn\Verification\VerificationProfile;
use Studiometa\Foehn\Verification\VerificationReport;
use Studiometa\Foehn\Verification\VerificationResult;
use Studiometa\Foehn\Verification\VerificationStatus;

describe('VerificationReport: the shape CI reads', function () {
    it('lists its keys in one fixed order', function () {
        $report = testVerificationReport(VerificationResult::pass('a-check', 'Fine.'))->toArray();

        expect(array_keys($report))->toBe(['schema', 'profile', 'status', 'summary', 'checks']);
        expect(array_keys($report['summary']))->toBe(['passed', 'failed', 'ignored']);
        expect(array_keys($report['checks'][0]))->toBe(['name', 'status', 'summary', 'details']);
    });

    it('carries the schema version and the profile that ran', function () {
        $report = testVerificationReport()->toArray();

        expect($report['schema'])->toBe(VerificationReport::SCHEMA);
        expect($report['profile'])->toBe('updates');
    });

    it('counts each status', function () {
        $report = testVerificationReport(
            VerificationResult::pass('a', 'Fine.'),
            VerificationResult::fail('b', 'Broken.'),
            new VerificationResult('c', VerificationStatus::Ignored, 'Not ours.'),
        );

        expect($report->summary())->toBe(['passed' => 1, 'failed' => 1, 'ignored' => 1]);
    });

    it('fails as a whole when one check fails', function () {
        expect(
            testVerificationReport(
                VerificationResult::pass('a', 'Fine.'),
                VerificationResult::fail('b', 'Broken.'),
            )->status(),
        )
            ->toBe(VerificationStatus::Fail);
    });

    it('passes when nothing failed, ignored findings included', function () {
        expect(
            testVerificationReport(
                VerificationResult::pass('a', 'Fine.'),
                new VerificationResult('b', VerificationStatus::Ignored, 'Not ours.'),
            )->status(),
        )
            ->toBe(VerificationStatus::Pass);
    });
});

describe('VerificationReport: determinism', function () {
    it('serialises to the same bytes twice', function () {
        $report = testVerificationReport(VerificationResult::pass('a-check', 'Fine.', [
            'diagnostics' => [],
            'ignored' => [],
        ]), VerificationResult::fail('b-check', 'Broken.', ['diagnostics' => [['type' => 'php_error']]]));

        expect($report->toJson())->toBe($report->toJson());
    });

    it('serialises to the same bytes whichever order the checks arrived in', function () {
        $first = VerificationResult::pass('a-check', 'Fine.');
        $second = VerificationResult::fail('b-check', 'Broken.');
        $third = new VerificationResult('c-check', VerificationStatus::Ignored, 'Not ours.');

        $forwards = new VerificationReport(VerificationProfile::Updates, [$first, $second, $third]);
        $backwards = new VerificationReport(VerificationProfile::Updates, [$third, $second, $first]);

        expect($backwards->toJson())->toBe($forwards->toJson());
    });

    it('orders checks by name rather than by arrival', function () {
        $report = testVerificationReport(
            VerificationResult::pass('zebra', 'Fine.'),
            VerificationResult::pass('apple', 'Fine.'),
        );

        expect(array_column($report->toArray()['checks'], 'name'))->toBe(['apple', 'zebra']);
    });

    it('ends with exactly one newline, so an artifact diff is a change in the site', function () {
        expect(testVerificationReport(VerificationResult::pass('a', 'Fine.'))->toJson())
            ->toEndWith("}\n")
            ->not->toEndWith("\n\n");
    });
});

describe('VerificationReport: what it must never contain', function () {
    /**
     * A report holding the kind of evidence the updates profile really puts in one.
     */
    $report = static fn(): VerificationReport => testVerificationReport(VerificationResult::fail(
        'runtime-diagnostics',
        '2 actionable diagnostics in this process.',
        [
            'diagnostics' => [
                [
                    'type' => 'deprecated_function',
                    'symbol' => 'old_function',
                    'message' => 'Use new_function() instead.',
                    'version' => '7.0.0',
                    'file' => 'wp-content/plugins/example/plugin.php',
                    'line' => 42,
                    'count' => 1,
                ],
            ],
            'ignored' => [
                [
                    'type' => 'php_error',
                    'symbol' => 'E_DEPRECATED',
                    'message' => 'Implicitly nullable parameter is deprecated',
                    'version' => '',
                    'file' => 'phar://wp/php/WP_CLI/Runner.php',
                    'line' => 88,
                    'count' => 3,
                ],
            ],
        ],
    ));

    it('holds no absolute path', function () use ($report) {
        $json = $report()->toJson();

        // No value starts at the filesystem root, and no Phar names where it is
        // installed — `phar://wp/…` is the archive, `phar:///usr/local/bin/wp/…` is a
        // machine.
        expect($json)->not->toMatch('#"/#');
        expect($json)->not->toContain('phar:///');
    });

    it('names neither ABSPATH nor the directory the tests run in', function () use ($report) {
        $json = $report()->toJson();

        expect($json)->not->toContain((string) constant('ABSPATH'));
        expect($json)->not->toContain(sys_get_temp_dir());
        expect($json)->not->toContain(dirname(__DIR__, 4));
    });

    it('holds no timestamp', function () use ($report) {
        $json = $report()->toJson();

        // A Unix timestamp, an ISO-8601 date, and anything calling itself a time: a
        // report that carried one would differ between two runs of an unchanged site,
        // and every artifact diff would then need reading before it could be dismissed.
        expect($json)->not->toMatch('/\b1[0-9]{9}\b/');
        expect($json)->not->toMatch('/\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/');
        expect($json)->not->toMatch('/"[^"]*(time|timestamp|date|generated|_at)[^"]*"\s*:/i');
    });
});
