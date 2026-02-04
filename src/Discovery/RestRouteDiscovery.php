<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use Studiometa\WPTempest\Attributes\AsRestRoute;
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
            foreach ($this->discoveryItems as $item) {
                $this->registerRoute($item['attribute'], $item['method']);
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

        $args = [
            'methods' => $attribute->getMethodConstant(),
            'callback' => $this->createCallback($className, $methodName),
            'permission_callback' => $this->createPermissionCallback($attribute, $className),
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
     * @param AsRestRoute $attribute
     * @param class-string $className
     * @return callable
     */
    private function createPermissionCallback(AsRestRoute $attribute, string $className): callable
    {
        // Public endpoint - no authentication required
        if ($attribute->permission === 'public') {
            return static fn() => true;
        }

        // No permission specified - require authentication
        if ($attribute->permission === null) {
            return static fn() => is_user_logged_in();
        }

        // Custom permission callback method on the class
        return static function (WP_REST_Request $request) use ($className, $attribute) {
            $instance = get($className);
            $permissionMethod = $attribute->permission;

            if (!method_exists($instance, $permissionMethod)) {
                return false;
            }

            return $instance->{$permissionMethod}($request);
        };
    }
}
