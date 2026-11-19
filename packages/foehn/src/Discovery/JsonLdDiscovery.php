<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Attributes\AsJsonLd;
use Studiometa\Foehn\Contracts\JsonLdProviderInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\JsonLd\JsonLd;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use WP_Post;

use function Tempest\Container\get;

/**
 * Discovers classes marked with #[AsJsonLd] and prints the schema.org JSON-LD graph of
 * the page.
 *
 * When Rank Math prints its graph, the nodes of a page go in it, through
 * `rank_math/json_ld`. Else Føhn prints its own graph in `<head>`. The nodes that
 * blocks add with JsonLd::add() go in a second graph in the footer, with or without
 * Rank Math.
 */
#[AsDiscovery(phase: DiscoveryPhase::Late)]
final class JsonLdDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * The provider classes, by post type.
     *
     * @var array<string, list<class-string<JsonLdProviderInterface>>>
     */
    private array $providers = [];

    /**
     * Whether Rank Math applied `rank_math/json_ld` during the request: then the nodes of
     * the page are in its graph.
     */
    private bool $mergedIntoRankMath = false;

    public function __construct(
        private readonly DiscoveryProblems $problems,
    ) {}

    /**
     * Discover #[AsJsonLd] attributes on classes.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        $attribute = $class->getAttribute(AsJsonLd::class);

        if ($attribute === null) {
            return;
        }

        if (!$class->implements(JsonLdProviderInterface::class)) {
            $this->problems->report(
                $location,
                $class->getName(),
                '#[AsJsonLd]',
                sprintf(
                    '%s has #[AsJsonLd] but does not implement %s, so it adds no JSON-LD.',
                    $class->getName(),
                    JsonLdProviderInterface::class,
                ),
                sprintf('Declare %s with `implements \\%s`.', $class->getName(), JsonLdProviderInterface::class),
            );

            return;
        }

        $this->addItem($location, [
            'attribute' => $attribute,
            'className' => $class->getName(),
        ]);
    }

    /**
     * Register the output on the hooks of the page.
     *
     * The footer graph is always registered: a block can add a node on a site with no
     * #[AsJsonLd] class.
     */
    public function apply(): void
    {
        foreach ($this->getItems() as $item) {
            /** @var AsJsonLd $attribute */
            $attribute = $item['attribute'];
            /** @var class-string<JsonLdProviderInterface> $className */
            $className = $item['className'];

            foreach ($attribute->getPostTypes() as $postType) {
                $this->providers[$postType][] = $className;
            }
        }

        if ($this->providers !== []) {
            add_filter('rank_math/json_ld', [$this, 'mergeIntoRankMath'], 99);
            add_action('wp_head', [$this, 'printPageGraph'], 99);
        }

        add_action('wp_footer', [$this, 'printBlockGraph'], 99);
    }

    /**
     * The nodes of the current page: those of the providers of its post type.
     *
     * @return list<array<array-key, mixed>>
     */
    public function pageNodes(): array
    {
        if (!is_singular()) {
            return [];
        }

        $post = get_queried_object();

        if (!$post instanceof WP_Post) {
            return [];
        }

        $nodes = [];

        foreach ($this->providers[$post->post_type] ?? [] as $className) {
            /** @var JsonLdProviderInterface $provider */
            $provider = get($className);

            foreach ($provider->nodes($post) as $node) {
                $nodes[] = JsonLd::normalize($node);
            }
        }

        return $nodes;
    }

    /**
     * Add the nodes of the page to the graph of Rank Math.
     *
     * Rank Math keys its nodes by name and prints the values, so the keys of Føhn only
     * have to be unique. Rank Math applies the filter on `rank_math/head`, which runs on
     * `wp_head` before printPageGraph().
     */
    public function mergeIntoRankMath(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $this->mergedIntoRankMath = true;

        foreach ($this->pageNodes() as $index => $node) {
            $data['foehn-' . $index] = $node;
        }

        return $data;
    }

    /**
     * Print the graph of the page in `<head>`, when the nodes are not in the graph of
     * Rank Math.
     *
     * Decided from what Rank Math did, and not from its settings: Rank Math does not load
     * its front end, and prints no graph, while its Schema module is off or its
     * registration is not done or skipped.
     */
    public function printPageGraph(): void
    {
        if ($this->mergedIntoRankMath) {
            return;
        }

        echo JsonLd::script($this->pageNodes());
    }

    /**
     * Print the nodes that blocks added, in the footer.
     */
    public function printBlockGraph(): void
    {
        echo JsonLd::script(JsonLd::flush());
    }
}
