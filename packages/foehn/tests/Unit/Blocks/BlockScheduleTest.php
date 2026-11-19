<?php

declare(strict_types=1);

use Studiometa\Foehn\Blocks\BlockSchedule;
use Studiometa\Foehn\Config\FoehnConfig;

/**
 * A block, as `render_block` hands it over, with the given date attributes.
 *
 * @return array<string, mixed>
 */
function scheduledBlock(?string $from = null, ?string $until = null): array
{
    return [
        'blockName' => 'core/paragraph',
        'attrs' => array_filter(['visibleFrom' => $from, 'visibleUntil' => $until], is_string(...)),
    ];
}

/**
 * A date relative to now, as the `datetime-local` input writes it, in the stub site time zone.
 */
function scheduleDate(string $modifier): string
{
    return new DateTimeImmutable($modifier, wp_timezone())->format('Y-m-d\TH:i:s');
}

beforeEach(function () {
    wp_stub_reset();
    $this->schedule = new BlockSchedule(new FoehnConfig());
    $this->scriptPath = WP_CONTENT_DIR . '/foehn/schedule.js';
});

afterEach(function () {
    if (is_file($this->scriptPath)) {
        unlink($this->scriptPath);
    }
});

describe('BlockSchedule: attributes', function () {
    it('adds both date attributes to every block type', function () {
        $args = $this->schedule->addAttributes(['attributes' => ['content' => ['type' => 'string']]]);

        expect($args['attributes'])->toBe([
            'content' => ['type' => 'string'],
            'visibleFrom' => ['type' => 'string'],
            'visibleUntil' => ['type' => 'string'],
        ]);
    });

    it('adds them to a block type registered without attributes', function () {
        expect($this->schedule->addAttributes([])['attributes'])->toHaveKeys(['visibleFrom', 'visibleUntil']);
    });

    it('wires the attribute filter, the render filter and the editor panel', function () {
        $this->schedule->register();

        $filters = array_column(array_column(wp_stub_get_calls('add_filter'), 'args'), 'hook');
        $actions = array_column(array_column(wp_stub_get_calls('add_action'), 'args'), 'hook');

        expect($filters)->toContain('register_block_type_args', 'render_block');
        expect($actions)->toContain('enqueue_block_editor_assets');
    });
});

describe('BlockSchedule: render filter', function () {
    it('leaves a block without dates alone', function () {
        expect($this->schedule->render('<p>Hi</p>', scheduledBlock()))->toBe('<p>Hi</p>');
        expect($this->schedule->nextChange())->toBeNull();
    });

    it('hides a block before its start date', function () {
        expect($this->schedule->render('<p>Hi</p>', scheduledBlock(from: scheduleDate('+1 day'))))->toBe('');
    });

    it('shows a block between its dates', function () {
        $block = scheduledBlock(from: scheduleDate('-1 day'), until: scheduleDate('+1 day'));

        expect($this->schedule->render('<p>Hi</p>', $block))->toBe('<p>Hi</p>');
    });

    it('hides a block from its end date on', function () {
        expect($this->schedule->render('<p>Hi</p>', scheduledBlock(until: scheduleDate('-1 minute'))))->toBe('');
    });

    it('reads the dates in the site time zone', function () {
        // Read in UTC, one hour from now in Tokyo would be ten hours from now: the
        // recorded date shows which reading was made.
        $GLOBALS['wp_stub_timezone'] = 'Asia/Tokyo';
        $from = scheduleDate('+1 hour');

        expect($this->schedule->render('<p>Hi</p>', scheduledBlock(from: $from)))->toBe('');
        expect($this->schedule->nextChange())
            ->toBe(new DateTimeImmutable($from, new DateTimeZone('Asia/Tokyo'))->getTimestamp());
    });

    it('treats an invalid date as no limit', function () {
        expect($this->schedule->render('<p>Hi</p>', scheduledBlock(from: 'not a date')))->toBe('<p>Hi</p>');
        expect($this->schedule->nextChange())->toBeNull();
    });

    it('keeps the earliest date at which a rendered block changes state', function () {
        $soon = scheduleDate('+1 hour');
        $later = scheduleDate('+2 days');

        $this->schedule->render('<p>A</p>', scheduledBlock(from: scheduleDate('-1 day'), until: $later));
        $this->schedule->render('<p>B</p>', scheduledBlock(from: $soon));
        $this->schedule->render('<p>C</p>', scheduledBlock(until: scheduleDate('-1 day')));

        expect($this->schedule->nextChange())->toBe(new DateTimeImmutable($soon, wp_timezone())->getTimestamp());
    });

    it('renders every block in the admin, where an editor previews it', function () {
        $GLOBALS['wp_stub_is_admin'] = true;

        expect($this->schedule->render('<p>Hi</p>', scheduledBlock(from: scheduleDate('+1 day'))))->toBe('<p>Hi</p>');
        expect($this->schedule->nextChange())->toBeNull();
    });

    it('answers visibility for a given moment', function () {
        $attributes = ['visibleFrom' => '2026-12-01T00:00', 'visibleUntil' => '2026-12-25T00:00'];
        $at = static fn(string $date): int => new DateTimeImmutable($date, wp_timezone())->getTimestamp();

        expect(BlockSchedule::isVisible($attributes, $at('2026-11-30 23:59:59')))->toBeFalse();
        expect(BlockSchedule::isVisible($attributes, $at('2026-12-01 00:00:00')))->toBeTrue();
        expect(BlockSchedule::isVisible($attributes, $at('2026-12-24 23:59:59')))->toBeTrue();
        expect(BlockSchedule::isVisible($attributes, $at('2026-12-25 00:00:00')))->toBeFalse();
    });
});

describe('BlockSchedule: editor panel', function () {
    it('enqueues the panel script in the head with its dependencies', function () {
        if (!is_dir(dirname($this->scriptPath))) {
            mkdir(dirname($this->scriptPath), 0o777, true);
        }

        file_put_contents($this->scriptPath, '// generated panel');

        $this->schedule->enqueue();

        $scripts = wp_stub_get_calls('wp_enqueue_script');

        expect($scripts)->toHaveCount(1);
        expect($scripts[0]['args']['handle'])->toBe(BlockSchedule::HANDLE);
        expect($scripts[0]['args']['src'])->toBe('http://example.com/wp-content/foehn/schedule.js');
        expect($scripts[0]['args']['deps'])->toContain('wp-hooks', 'wp-compose', 'wp-block-editor');
        expect($scripts[0]['args']['in_footer'])->toBeFalse();
    });

    it('skips a missing panel script', function () {
        $this->schedule->enqueue();

        expect(wp_stub_get_calls('wp_enqueue_script'))->toBe([]);
    });

    it('reports a missing panel script in debug mode', function () {
        $schedule = new BlockSchedule(new FoehnConfig(debug: true));
        $notices = [];
        set_error_handler(static function (int $level, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        });

        try {
            $schedule->enqueue();
        } finally {
            restore_error_handler();
        }

        expect($notices)->toHaveCount(1);
        expect($notices[0])->toContain('schedule.js');
    });
});
