<?php

declare(strict_types=1);

use Studiometa\Foehn\Verification\ReportWriter;
use Studiometa\Foehn\Verification\VerificationFailure;
use Studiometa\Foehn\Verification\VerificationResult;

/**
 * Everything in a directory, so a leftover temporary file cannot hide.
 *
 * @return list<string>
 */
function reportDirectoryEntries(string $directory): array
{
    $entries = array_values(array_diff((array) scandir($directory), ['.', '..']));

    sort($entries);

    return $entries;
}

beforeEach(function () {
    $this->directory = testVerificationDirectory();
    $this->writer = new ReportWriter();
    $this->report = testVerificationReport(VerificationResult::pass('a-check', 'Fine.'));
});

afterEach(function () {
    // Restored first: a test that made the directory read-only would otherwise leave a
    // directory nothing can delete.
    @chmod($this->directory, 0o777);
    removeTestDirectory($this->directory);
});

describe('ReportWriter: where the report lands', function () {
    it('writes an absolute path where it was asked to', function () {
        $path = $this->directory . '/report.json';

        expect($this->writer->write($path, $this->report))->toBe($path);
        expect(file_get_contents($path))->toBe($this->report->toJson());
    });

    it('resolves a relative path from ABSPATH rather than the working directory', function () {
        // A deployment script and a CI job run from wherever they happen to be, so the
        // report has to land in the same place either way.
        expect($this->writer->resolve('build/foehn-verification.json'))
            ->toBe(constant('ABSPATH') . 'build/foehn-verification.json');
    });

    it('leaves an absolute path alone', function () {
        expect($this->writer->resolve('/var/tmp/report.json'))->toBe('/var/tmp/report.json');
    });
});

describe('ReportWriter: one step or none', function () {
    it('leaves no temporary file beside the report', function () {
        $this->writer->write($this->directory . '/report.json', $this->report);

        expect(reportDirectoryEntries($this->directory))->toBe(['report.json']);
    });

    it('replaces a report the process could not have opened for writing', function () {
        // The proof that the write goes through rename() rather than through an open of
        // the target: a read-only file cannot be written to, but it can be replaced.
        $path = $this->directory . '/report.json';

        file_put_contents($path, "previous\n");
        chmod($path, 0o444);

        $this->writer->write($path, $this->report);

        expect(file_get_contents($path))->toBe($this->report->toJson());
    });

    it('refuses a directory that does not exist rather than creating one', function () {
        expect(fn() => $this->writer->write($this->directory . '/missing/report.json', $this->report))
            ->toThrow(VerificationFailure::class, 'is not a directory');
    });

    it('leaves the previous report and no temporary file when it cannot write', function () {
        $path = $this->directory . '/report.json';

        file_put_contents($path, "previous\n");

        if (!chmod($this->directory, 0o555) || is_writable($this->directory)) {
            // Running as a user the mode does not apply to — root, or a filesystem
            // without permissions. The assertion below would pass for the wrong reason.
            expect(true)->toBeTrue();

            return;
        }

        expect(fn() => $this->writer->write($path, $this->report))
            ->toThrow(VerificationFailure::class, 'is not writable');

        expect(file_get_contents($path))->toBe("previous\n");
        expect(reportDirectoryEntries($this->directory))->toBe(['report.json']);
    });
});
