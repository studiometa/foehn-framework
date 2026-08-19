<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Hooks\S3UploadsEndpoint;

/**
 * The class reads the environment the way the generated wp-config.php does, so the
 * tests set the environment rather than injecting a double. $_ENV is what dotenv
 * populates, and it is restored after each test because the suite shares a process.
 */
beforeEach(function () {
    $this->keys = ['S3_UPLOADS_ENDPOINT', 'S3_UPLOADS_PATH_STYLE', 'S3_UPLOADS_CHECKSUMS'];
    $this->originals = [];

    foreach ($this->keys as $key) {
        $this->originals[$key] = $_ENV[$key] ?? null;
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }
});

afterEach(function () {
    foreach ($this->keys as $key) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);

        if ($this->originals[$key] !== null) {
            $_ENV[$key] = $this->originals[$key];
        }
    }
});

describe('S3UploadsEndpoint', function () {
    it('leaves the parameters alone without an endpoint', function () {
        // A site on AWS proper has every default right already, and a site with the
        // hook class opted in but nothing configured must not break its uploads.
        expect(new S3UploadsEndpoint()->endpoint(['region' => 'eu-west-1']))->toBe(['region' => 'eu-west-1']);
    });

    it('leaves the parameters alone for an empty endpoint', function () {
        $_ENV['S3_UPLOADS_ENDPOINT'] = '';

        expect(new S3UploadsEndpoint()->endpoint(['region' => 'eu-west-1']))->toBe(['region' => 'eu-west-1']);
    });

    it('sets the endpoint from the environment', function () {
        $_ENV['S3_UPLOADS_ENDPOINT'] = 'https://s3.fr-par.scw.cloud';

        $params = new S3UploadsEndpoint()->endpoint([]);

        expect($params['endpoint'])->toBe('https://s3.fr-par.scw.cloud');
    });

    it('keeps the parameters it was given', function () {
        $_ENV['S3_UPLOADS_ENDPOINT'] = 'https://s3.fr-par.scw.cloud';

        $params = new S3UploadsEndpoint()->endpoint(['region' => 'fr-par', 'version' => 'latest']);

        expect($params['region'])->toBe('fr-par');
        expect($params['version'])->toBe('latest');
    });

    it('addresses the bucket by hostname by default', function () {
        $_ENV['S3_UPLOADS_ENDPOINT'] = 'https://s3.fr-par.scw.cloud';

        // R2 and Scaleway serve virtual-hosted style; path style is the exception.
        expect(new S3UploadsEndpoint()->endpoint([])['use_path_style_endpoint'])->toBeFalse();
    });

    it('addresses the bucket by path when asked', function () {
        $_ENV['S3_UPLOADS_ENDPOINT'] = 'http://minio:9000';
        $_ENV['S3_UPLOADS_PATH_STYLE'] = 'true';

        expect(new S3UploadsEndpoint()->endpoint([])['use_path_style_endpoint'])->toBeTrue();
    });

    it('reads the flags as strings, which is all an .env file holds', function () {
        $_ENV['S3_UPLOADS_ENDPOINT'] = 'http://minio:9000';
        $_ENV['S3_UPLOADS_PATH_STYLE'] = 'false';

        // "false" is a non-empty string, so a plain cast would make it true and
        // silently address the bucket the wrong way.
        expect(new S3UploadsEndpoint()->endpoint([])['use_path_style_endpoint'])->toBeFalse();
    });

    it('leaves the AWS SDK checksums on by default', function () {
        $_ENV['S3_UPLOADS_ENDPOINT'] = 'https://s3.fr-par.scw.cloud';

        expect(new S3UploadsEndpoint()->endpoint([]))->not->toHaveKey('request_checksum_calculation');
    });

    it('disables the checksums for a provider that rejects them', function () {
        $_ENV['S3_UPLOADS_ENDPOINT'] = 'http://minio:9000';
        $_ENV['S3_UPLOADS_CHECKSUMS'] = 'false';

        $params = new S3UploadsEndpoint()->endpoint([]);

        expect($params['request_checksum_calculation'])->toBe('when_required');
        expect($params['response_checksum_validation'])->toBe('when_required');
    });

    it('reads the endpoint from the process environment too', function () {
        // Container platforms supply real environment variables rather than an .env,
        // and phpdotenv is not in play at all there.
        putenv('S3_UPLOADS_ENDPOINT=https://from-getenv.example.com');

        expect(new S3UploadsEndpoint()->endpoint([])['endpoint'])->toBe('https://from-getenv.example.com');
    });

    it('registers on the filter the plugin applies', function () {
        // The name is the contract with humanmade/s3-uploads. A typo here is a class
        // that runs never, and uploads that go to AWS instead of the endpoint.
        $method = new ReflectionMethod(S3UploadsEndpoint::class, 'endpoint');
        $filter = $method->getAttributes(AsFilter::class)[0]->newInstance();

        expect($filter->hook)->toBe('s3_uploads_s3_client_params');
    });
});
