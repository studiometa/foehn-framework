<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ImageTransformer;
use Studiometa\Foehn\Images\GlideConfig;
use Studiometa\Foehn\Images\GlideTransformer;
use Studiometa\Foehn\Images\NullTransformer;

describe('NullTransformer', function () {
    // La valeur par défaut ne change rien : un gabarit écrit contre l'interface
    // s'affiche correctement sur un projet qui ne transforme aucune image.
    it('rend l\'URL telle quelle', function () {
        expect(new NullTransformer()->url('http://example.com/a.jpg', ['w' => 400]))->toBe('http://example.com/a.jpg');
    });

    it('implémente le contrat', function () {
        expect(new NullTransformer())->toBeInstanceOf(ImageTransformer::class);
    });
});

describe('GlideTransformer', function () {
    $media = 'http://example.com/wp-content/uploads/2016/06/photo.jpg';

    it('signe l\'URL qu\'il produit', function () use ($media) {
        $url = new GlideTransformer(new GlideConfig())->url($media, ['w' => 400, 'h' => 267, 'fit' => 'crop']);

        expect($url)->toContain('/_image/2016/06/photo.jpg');
        expect($url)->toContain('w=400');
        // Sans signature, `?w=9999` est une invitation à dépenser du CPU.
        expect($url)->toContain('s=');
    });

    // La signature couvre les paramètres : deux URLs qui demandent la même chose
    // doivent produire la même chaîne, sinon la même vignette est mise en cache
    // deux fois, sous deux signatures.
    it('produit la même URL quel que soit l\'ordre des paramètres', function () use ($media) {
        $transformer = new GlideTransformer(new GlideConfig());

        expect($transformer->url($media, ['w' => 400, 'h' => 267]))
            ->toBe($transformer->url($media, ['h' => 267, 'w' => 400]));
    });

    it('laisse passer une image extérieure aux médias du site', function () {
        $externe = 'https://ailleurs.test/photo.jpg';

        expect(new GlideTransformer(new GlideConfig())->url($externe, ['w' => 400]))->toBe($externe);
    });

    it('laisse passer une URL sans transformation demandée', function () use ($media) {
        expect(new GlideTransformer(new GlideConfig())->url($media, []))->toBe($media);
    });

    // Un `..` dans un chemin qui atteint le système de fichiers est la façon dont
    // un transformateur devient un moyen de lire ce qu'on ne lui a pas montré.
    it('refuse un chemin qui remonte hors des médias', function () {
        $remonte = 'http://example.com/wp-content/uploads/../../wp-config.php';

        expect(new GlideTransformer(new GlideConfig())->url($remonte, ['w' => 10]))->toBe($remonte);
    });
});
