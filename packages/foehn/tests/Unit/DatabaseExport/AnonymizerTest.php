<?php

declare(strict_types=1);

use Studiometa\Foehn\DatabaseExport\Anonymizer;

describe('Anonymizer', function () {
    $original = 'jane.doe@example.com';

    foreach (Anonymizer::cases() as $anonymizer) {
        it("{$anonymizer->name} gives the same result for the same value", function () use ($anonymizer, $original) {
            expect($anonymizer->apply($original))->toBe($anonymizer->apply($original));
            expect($anonymizer->apply($original, '42'))->toBe($anonymizer->apply($original, '42'));
        });

        it("{$anonymizer->name} leaves nothing of the original value", function () use ($anonymizer, $original) {
            $result = (string) $anonymizer->apply($original);

            expect($result)->not->toContain('jane');
            expect($result)->not->toContain('doe');
            expect($result)->not->toContain('example.com');
        });

        it("{$anonymizer->name} keeps NULL and an empty string", function () use ($anonymizer) {
            expect($anonymizer->apply(null))->toBeNull();
            expect($anonymizer->apply(''))->toBe('');
        });
    }

    it('derives the token from the seed when one is given', function () {
        expect(Anonymizer::Email->apply('jane@example.com', '42'))->toBe('user-42@example.invalid');
        expect(Anonymizer::Username->apply('jane', '42'))->toBe('user-42');
        expect(Anonymizer::Name->apply('Jane Doe', '42'))->toBe('User 42');
    });

    it('hashes the value when there is no seed, so equal values stay equal', function () {
        $first = Anonymizer::Email->apply('jane@example.com');

        expect($first)->toMatch('/^user-[0-9a-f]{12}@example\.invalid$/');
        expect(Anonymizer::Email->apply('john@example.com'))->not->toBe($first);
    });

    it('writes a reserved documentation address for an IP', function () {
        expect(Anonymizer::Ip->apply('203.0.113.9'))->toBe('192.0.2.1');
    });

    it('empties a secret', function () {
        expect(Anonymizer::Empty->apply('$wp$2y$10$abcdefghijklmnopqrstuv'))->toBe('');
    });

    it('marks only the replacements of who a row is about as identity', function () {
        expect(Anonymizer::Email->isIdentity())->toBeTrue();
        expect(Anonymizer::Username->isIdentity())->toBeTrue();
        expect(Anonymizer::Name->isIdentity())->toBeTrue();
        expect(Anonymizer::Ip->isIdentity())->toBeFalse();
        expect(Anonymizer::Empty->isIdentity())->toBeFalse();
    });
});
