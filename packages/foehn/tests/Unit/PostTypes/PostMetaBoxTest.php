<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\PostTypes\PostMetaBox;
use Tests\Fixtures\PostMeta\Event;

/**
 * The meta box of the Event fixture: every key that declares a control.
 */
function eventMetaBox(): PostMetaBox
{
    $fields = [];

    foreach (new ReflectionClass(Event::class)->getAttributes(AsPostMeta::class) as $attribute) {
        $field = $attribute->newInstance();

        if ($field->control !== null) {
            $fields[] = $field;
        }
    }

    return new PostMetaBox('event', $fields);
}

/**
 * A request the meta box form sent, with a valid nonce and the values slashed as WordPress does.
 *
 * @param array<string, string> $values
 * @return array<string, mixed>
 */
function metaBoxRequest(array $values): array
{
    return [
        PostMetaBox::NONCE => wp_create_nonce(PostMetaBox::ID),
        PostMetaBox::FIELD => array_map('addslashes', $values),
    ];
}

function renderedMetaBox(int $postId = 7): string
{
    $post = new WP_Post();
    $post->ID = $postId;

    ob_start();
    eventMetaBox()->render($post);

    return (string) ob_get_clean();
}

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wp_stub_user_can'] = ['edit_posts' => true, 'edit_post' => true];
});

describe('PostMetaBox rendering', function () {
    it('prints the nonce and one form-table row for each field', function () {
        $html = renderedMetaBox();

        expect($html)
            ->toStartWith('<input type="hidden" name="foehn_post_meta_nonce" value="nonce:foehn-post-meta">')
            ->toContain('<table class="form-table" role="presentation"><tbody>')
            ->toEndWith('</tbody></table>');
        expect(substr_count($html, '<tr>'))->toBe(5);
    });

    it('draws a field with the settings page renderer, under the request field', function () {
        $GLOBALS['wp_stub_post_meta'][7]['subtitle'] = 'Opening night';

        expect(renderedMetaBox())
            ->toContain(
                '<tr><th scope="row"><label for="foehn_meta[subtitle]">Subtitle</label></th><td>'
                . '<input type="text" id="foehn_meta[subtitle]" name="foehn_meta[subtitle]" value="Opening night" class="regular-text" aria-describedby="foehn_meta[subtitle]-description" />'
                . '<p class="description" id="foehn_meta[subtitle]-description">Under the title</p></td></tr>',
            );
    });

    it('labels a field with its key when no label was declared', function () {
        expect(renderedMetaBox())->toContain('<label for="foehn_meta[code]">code</label>');
    });

    it('puts a hidden value before a checkbox, so unchecking it is posted', function () {
        $GLOBALS['wp_stub_post_meta'][7]['featured'] = '1';

        expect(renderedMetaBox())
            ->toContain(
                '<tr><th scope="row">Featured</th><td><input type="hidden" name="foehn_meta[featured]" value="0" />'
                . '<fieldset><legend class="screen-reader-text"><span>Featured</span></legend>'
                . '<label for="foehn_meta[featured]"><input type="checkbox" id="foehn_meta[featured]" name="foehn_meta[featured]" value="1" checked="checked" /> Featured</label></fieldset></td></tr>',
            );
    });

    it('draws a number and a select for their types', function () {
        $GLOBALS['wp_stub_post_meta'][7]['seats'] = '40';
        $GLOBALS['wp_stub_post_meta'][7]['room'] = 'small';

        expect(renderedMetaBox())
            ->toContain('name="foehn_meta[seats]" value="40" step="1" class="small-text"')
            ->toContain('<option value="small" selected="selected">Small hall</option>');
    });

    it('leaves out a field the user cannot write', function () {
        expect(renderedMetaBox())->not->toContain('foehn_meta[budget]');

        $GLOBALS['wp_stub_user_can']['manage_options'] = true;

        expect(renderedMetaBox())->toContain('name="foehn_meta[budget]" value="" step="any"');
    });

    it('adds the meta box to its post type only when the user can write a field', function () {
        eventMetaBox()->add();

        $box = wp_stub_get_calls('add_meta_box')[0]['args'];

        expect($box['id'])->toBe('foehn-post-meta');
        expect($box['title'])->toBe('Fields');
        expect($box['screen'])->toBe('event');

        wp_stub_reset();
        eventMetaBox()->add();

        expect(wp_stub_get_calls('add_meta_box'))->toBeEmpty();
    });
});

describe('PostMetaBox saving', function () {
    it('saves each posted field through the sanitiser of its control', function () {
        eventMetaBox()->save(7, metaBoxRequest([
            'subtitle' => "  It's <b>on</b> ",
            'featured' => '1',
            'seats' => '-12',
            'room' => 'small',
        ]));

        expect($GLOBALS['wp_stub_post_meta'][7])->toBe([
            'subtitle' => "It's on",
            'featured' => true,
            'seats' => 12,
            'room' => 'small',
        ]);
    });

    it('stores an unchecked checkbox as false', function () {
        $GLOBALS['wp_stub_post_meta'][7]['featured'] = true;

        eventMetaBox()->save(7, metaBoxRequest(['featured' => '0']));

        expect($GLOBALS['wp_stub_post_meta'][7]['featured'])->toBeFalse();
    });

    it('keeps a select to one of its options', function () {
        eventMetaBox()->save(7, metaBoxRequest(['room' => 'roof']));

        expect($GLOBALS['wp_stub_post_meta'][7]['room'])->toBe('');
    });

    it('hands the raw value to a key with a sanitize method, which update_post_meta runs', function () {
        // WordPress runs the registered sanitize_callback inside update_post_meta().
        // Sanitising here too would run the control's sanitiser before the key's own.
        eventMetaBox()->save(7, metaBoxRequest(['code' => ' <ab> ']));

        expect($GLOBALS['wp_stub_post_meta'][7]['code'])->toBe(' <ab> ');
    });

    it('does not touch a key absent from the request', function () {
        $GLOBALS['wp_stub_post_meta'][7] = ['subtitle' => 'Kept', 'featured' => true];

        eventMetaBox()->save(7, metaBoxRequest(['seats' => '3']));

        expect($GLOBALS['wp_stub_post_meta'][7])->toBe(['subtitle' => 'Kept', 'featured' => true, 'seats' => 3]);
    });

    it('deletes a key posted empty, so its default applies again', function () {
        $GLOBALS['wp_stub_post_meta'][7]['subtitle'] = 'Old';

        eventMetaBox()->save(7, metaBoxRequest(['subtitle' => '']));

        expect($GLOBALS['wp_stub_post_meta'][7])->not->toHaveKey('subtitle');
        expect(wp_stub_get_calls('delete_post_meta')[0]['args']['key'])->toBe('subtitle');
    });

    it('saves nothing without a valid nonce', function () {
        $request = metaBoxRequest(['subtitle' => 'Hello']);

        eventMetaBox()->save(7, [PostMetaBox::FIELD => $request[PostMetaBox::FIELD]]);
        eventMetaBox()->save(7, [...$request, PostMetaBox::NONCE => 'forged']);

        expect(wp_stub_get_calls('update_post_meta'))->toBeEmpty();
    });

    it('saves nothing when the user cannot edit the post', function () {
        $GLOBALS['wp_stub_user_can']['edit_post'] = false;

        eventMetaBox()->save(7, metaBoxRequest(['subtitle' => 'Hello']));

        expect(wp_stub_get_calls('update_post_meta'))->toBeEmpty();
    });

    it('skips a key whose capability the user does not have', function () {
        eventMetaBox()->save(7, metaBoxRequest(['budget' => '100', 'subtitle' => 'Hello']));

        expect($GLOBALS['wp_stub_post_meta'][7])->toBe(['subtitle' => 'Hello']);

        $GLOBALS['wp_stub_user_can']['manage_options'] = true;
        eventMetaBox()->save(7, metaBoxRequest(['budget' => '99.5']));

        expect($GLOBALS['wp_stub_post_meta'][7]['budget'])->toBe(99.5);
    });

    it('ignores a value that is not a scalar', function () {
        eventMetaBox()->save(7, [
            PostMetaBox::NONCE => wp_create_nonce(PostMetaBox::ID),
            PostMetaBox::FIELD => ['subtitle' => ['a', 'b']],
        ]);

        expect(wp_stub_get_calls('update_post_meta'))->toBeEmpty();
    });
});
