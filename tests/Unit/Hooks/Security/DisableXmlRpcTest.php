<?php

declare(strict_types=1);

use Studiometa\WPTempest\Hooks\Security\DisableXmlRpc;

describe('DisableXmlRpc', function () {
    it('returns false for xmlrpc_enabled filter', function () {
        $hooks = new DisableXmlRpc();

        expect($hooks->disableXmlRpc())->toBeFalse();
    });

    it('removes X-Pingback header', function () {
        $hooks = new DisableXmlRpc();

        $headers = [
            'X-Pingback' => 'https://example.com/xmlrpc.php',
            'Content-Type' => 'text/html',
        ];

        $result = $hooks->removePingbackHeader($headers);

        expect($result)->not->toHaveKey('X-Pingback');
        expect($result)->toHaveKey('Content-Type');
    });

    it('returns empty string for pingback_url', function () {
        $hooks = new DisableXmlRpc();

        expect($hooks->removePingbackUrl('https://example.com/xmlrpc.php', 'pingback_url'))->toBe('');
    });

    it('preserves other bloginfo values', function () {
        $hooks = new DisableXmlRpc();

        expect($hooks->removePingbackUrl('https://example.com', 'url'))->toBe('https://example.com');
        expect($hooks->removePingbackUrl('My Site', 'name'))->toBe('My Site');
    });

    it('has correct filter attributes', function () {
        $reflection = new ReflectionClass(DisableXmlRpc::class);

        $xmlrpcAttr = $reflection->getMethod('disableXmlRpc')
            ->getAttributes(\Studiometa\WPTempest\Attributes\AsFilter::class);

        expect($xmlrpcAttr)->toHaveCount(1);
        expect($xmlrpcAttr[0]->newInstance()->hook)->toBe('xmlrpc_enabled');

        $headersAttr = $reflection->getMethod('removePingbackHeader')
            ->getAttributes(\Studiometa\WPTempest\Attributes\AsFilter::class);

        expect($headersAttr)->toHaveCount(1);
        expect($headersAttr[0]->newInstance()->hook)->toBe('wp_headers');

        $bloginfoAttr = $reflection->getMethod('removePingbackUrl')
            ->getAttributes(\Studiometa\WPTempest\Attributes\AsFilter::class);

        expect($bloginfoAttr)->toHaveCount(1);
        expect($bloginfoAttr[0]->newInstance()->hook)->toBe('bloginfo_url');
        expect($bloginfoAttr[0]->newInstance()->acceptedArgs)->toBe(2);
    });
});
