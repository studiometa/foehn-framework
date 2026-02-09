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

use function Tempest\get;

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
     * @param ReflectionClass<object> $class
     */
    public function discover(ReflectionClass $class): void
    {
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }

            $attributes = $method->getAttributes(AsRestRoute::class);

            foreach ($attributes as $reflectionAttribute) {
                $attribute = $reflectionAttribute->newInstance();

                $this->addItem([
                    'namespace' => $attribute->namespace,
                    'route' => $attribute->route,
                    'httpMethod' => $attribute->getMethodConstant(),
                    'className' => $method->getDeclaringClass()->getName(),
                    'methodName' => $method->getName(),
                    'permission' => $attribute->permission,
                    'args' => $attribute->args,
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
        });
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

    /**
     * Convert a discovered item to a cacheable format.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function itemToCacheable(array $item): array
    {
        return [
            'namespace' => $item['namespace'],
            'route' => $item['route'],
            'httpMethod' => $item['httpMethod'],
            'className' => $item['className'],
            'methodName' => $item['methodName'],
            'permission' => $item['permission'],
            'args' => $item['args'],
        ];
    }
}
