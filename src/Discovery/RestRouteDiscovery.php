<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use Studiometa\WPTempest\Attributes\AsRestRoute;
use Studiometa\WPTempest\Discovery\Concerns\CacheableDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use WP_REST_Request;

use function Tempest\get;

/**
 * Discovers methods marked with #[AsRestRoute] attribute
 * and registers them as WordPress REST API endpoints.
 */
final class RestRouteDiscovery implements Discovery
{
    use IsDiscovery;
    use CacheableDiscovery;

    /**
     * Discover REST route attributes on methods.
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        foreach ($class->getPublicMethods() as $method) {
            $attributes = $method->getAttributes(AsRestRoute::class);

            foreach ($attributes as $attribute) {
                $this->discoveryItems->add($location, [
                    'attribute' => $attribute,
                    'method' => $method,
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
            foreach ($this->getAllItems() as $item) {
                // Handle cached format
                if (isset($item['className'])) {
                    $this->registerRouteFromCache($item);
                } else {
                    $this->registerRoute($item['attribute'], $item['method']);
                }
            }
        });
    }

    /**
     * Register a single REST route.
     *
     * @param AsRestRoute $attribute
     * @param MethodReflector $method
     */
    private function registerRoute(AsRestRoute $attribute, MethodReflector $method): void
    {
        $className = $method->getDeclaringClass()->getName();
        $methodName = $method->getName();

        $this->doRegisterRoute(
            $attribute->namespace,
            $attribute->route,
            $attribute->getMethodConstant(),
            $className,
            $methodName,
            $attribute->permission,
            $attribute->args,
        );
    }

    /**
     * Register REST route from cached data.
     *
     * @param array<string, mixed> $item
     */
    private function registerRouteFromCache(array $item): void
    {
        $this->doRegisterRoute(
            $item['namespace'],
            $item['route'],
            $item['httpMethod'],
            $item['className'],
            $item['methodName'],
            $item['permission'],
            $item['args'],
        );
    }

    /**
     * Actually register the REST route.
     *
     * @param class-string $className
     * @param array<string, mixed> $routeArgs
     */
    private function doRegisterRoute(
        string $namespace,
        string $route,
        string $httpMethod,
        string $className,
        string $methodName,
        ?string $permission,
        array $routeArgs,
    ): void {
        $args = [
            'methods' => $httpMethod,
            'callback' => $this->createCallback($className, $methodName),
            'permission_callback' => $this->createPermissionCallback($permission, $className),
        ];

        if (!empty($routeArgs)) {
            $args['args'] = $routeArgs;
        }

        register_rest_route($namespace, $route, $args);
    }

    /**
     * Create the endpoint callback.
     *
     * @param class-string $className
     * @param string $methodName
     * @return callable
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
     * @param string|null $permission
     * @param class-string $className
     * @return callable
     */
    private function createPermissionCallback(?string $permission, string $className): callable
    {
        // Public endpoint - no authentication required
        if ($permission === 'public') {
            return static fn() => true;
        }

        // No permission specified - require authentication
        if ($permission === null) {
            return static fn() => is_user_logged_in();
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

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function itemToCacheable(array $item): array
    {
        /** @var AsRestRoute $attribute */
        $attribute = $item['attribute'];
        /** @var MethodReflector $method */
        $method = $item['method'];

        return [
            'namespace' => $attribute->namespace,
            'route' => $attribute->route,
            'httpMethod' => $attribute->getMethodConstant(),
            'className' => $method->getDeclaringClass()->getName(),
            'methodName' => $method->getName(),
            'permission' => $attribute->permission,
            'args' => $attribute->args,
        ];
    }
}
