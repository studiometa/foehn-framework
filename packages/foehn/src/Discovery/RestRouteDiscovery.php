<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use ReflectionMethod;
use Studiometa\Foehn\Attributes\AsRestRoute;
use Studiometa\Foehn\Config\RestConfig;
use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use WP_REST_Request;

use function Tempest\Container\get;

/**
 * Discovers methods marked with #[AsRestRoute] attribute
 * and registers them as WordPress REST API endpoints.
 */
final class RestRouteDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    public function __construct(
        private readonly ?RestConfig $config = null,
    ) {}

    /**
     * Discover REST route attributes on methods.
     *
     * @param DiscoveryLocation $location
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }

            $attributes = $method->getAttributes(AsRestRoute::class);

            foreach ($attributes as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();

                $this->addItem($location, [
                    'attribute' => $attribute,
                    'className' => $method->getDeclaringClass()->getName(),
                    'methodName' => $method->getName(),
                ]);
            }
        }
    }

    /**
     * Apply discovered REST routes by registering them.
     */
    public function apply(): void
    {
        add_action('rest_api_init', function (): void {
            foreach ($this->getItems() as $item) {
                /** @var AsRestRoute $attribute */
                $attribute = $item['attribute'];

                $this->registerRoute($attribute, $item['className'], $item['methodName']);
            }
        });
    }

    /**
     * Register a single REST route.
     *
     * @param class-string $className
     */
    private function registerRoute(AsRestRoute $attribute, string $className, string $methodName): void
    {
        $args = [
            'methods' => $attribute->getMethodConstant(),
            'callback' => $this->createCallback($className, $methodName),
            'permission_callback' => $this->createPermissionCallback($attribute->permission, $className),
        ];

        if (!empty($attribute->args)) {
            $args['args'] = $attribute->args;
        }

        register_rest_route($attribute->namespace, $attribute->route, $args);
    }

    /**
     * Create the endpoint callback.
     *
     * @param class-string $className
     */
    private function createCallback(string $className, string $methodName): callable
    {
        return static function (WP_REST_Request $request) use ($className, $methodName) {
            $instance = get($className);

            return $instance->{$methodName}($request);
        };
    }

    /**
     * Create the permission callback.
     *
     * @param class-string $className
     */
    private function createPermissionCallback(?string $permission, string $className): callable
    {
        // Public endpoint - no authentication required
        if ($permission === 'public') {
            return static fn() => true;
        }

        // No permission specified - use default capability from config
        if ($permission === null) {
            // If no config, default to 'edit_posts'
            // If config exists, use its defaultCapability (which may be null for is_user_logged_in fallback)
            $defaultCapability = $this->config !== null ? $this->config->defaultCapability : 'edit_posts';

            if ($defaultCapability === null) {
                return static fn() => is_user_logged_in();
            }

            return static fn() => current_user_can($defaultCapability);
        }

        // Custom permission callback method on the class
        return static function (WP_REST_Request $request) use ($className, $permission) {
            $instance = get($className);

            if (!method_exists($instance, $permission)) {
                return false;
            }

            return $instance->{$permission}($request);
        };
    }
}
