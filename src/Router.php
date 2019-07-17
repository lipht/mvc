<?php
namespace Lipht\Mvc;

use Closure;
use Exception;
use Lipht\Annotation;
use Lipht\AnnotationReader;
use ReflectionClass;
use ReflectionException;

class Router {
    /**
     * @var string|null $baseUrl
     */
    private $baseUrl = null;

    /**
     * @var Route[] $routes
     */
    private $routes = [];

    /**
     * Router constructor.
     * @param string $appRoot
     * @param string|null $docRoot
     */
    public function __construct($appRoot, $docRoot = null) {
        register_shutdown_function(function() use($appRoot) {
            chdir($appRoot);
            $this->serve();
        });

        $docRoot = $docRoot ?? rtrim($_SERVER['DOCUMENT_ROOT'], '/');

        $this->registerBaseDir($appRoot, $docRoot);
    }

    /**
     * @param string $path
     * @param string $method
     * @param callable $callback
     * @param Closure[] $middleware
     * @throws Exception
     */
    public function map($path, $method, $callback, $middleware = []) {
        if (is_array($path)) {
            foreach($path as $piece)
                $this->map($piece, $method, $callback, $middleware);

            return;
        }

        if (substr($path, 0, 1) !== '/') {
            $path = '/'.$path;
        }

        $this->routes[] = new Route($path, $method, $callback, $middleware);
    }

    /**
     * @param string|string[] $className
     * @param Closure[] $middleware
     * @throws ReflectionException
     * @throws Exception
     */
    public function mapController($className, $middleware = []) {
        if (is_array($className)) {
            foreach ($className as $class) {
                $this->mapController($class, $middleware);
            }
            return;
        }

        static $TAG_NAME = 'route';

        $instance = new $className($this);
        $meta = new ReflectionClass($className);
        $annotation = AnnotationReader::parse($meta);
        $tags = $annotation->tags;
        if (empty($tags)) {
            $tags[] = (object)['name' => $TAG_NAME, 'args' => []];
        }

        foreach($tags as $tag) {
            if ($tag->name != $TAG_NAME)
                continue;

            foreach ($meta->getMethods() as $method) {
                if ($method->isStatic()
                    || !$method->isPublic())
                    continue;

                if (!isset($annotation->methods->{$method->getName()}))
                    continue;

                /** @var Annotation[] $children */
                $children = $annotation->methods->{$method->getName()}->tags;

                foreach ($children as $childTag) {
                    if ($childTag->name != $TAG_NAME)
                        continue;

                    $this->mapControllerAction(
                        [$instance, $method->getName()],
                        $tag,
                        $childTag,
                        $middleware
                    );
                }
            }
        }
    }

    /**
     * @return string|null
     */
    public function getBaseUrl() {
        return $this->baseUrl;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function serve() {
        $this->handlePreflight();

        $uri = $this->getRelativePath();
        $route = $this->findRoute($uri);
        $route->invoke($uri);
    }

    /**
     * @param string $path
     * @param string|null $method
     * @return Route
     * @throws Exception
     */
    public function findRoute($path, $method = null) {
        $method = $method ?? $_SERVER['REQUEST_METHOD'];
        foreach ($this->routes as $route) {
            if ($route->match($path, $method))
                return $route;
        }

        return new Route('404', $method, function() {
            Header::send('HTTP/1.1 404 Not Found');
        });
    }

    /**
     * @param string $path
     * @param string $root
     */
    protected function registerBaseDir($path, $root) {
        $forwardSlashedAppPath = str_replace('\\', '/', $path);
        $forwardSlashedDocumentRoot = str_replace('\\', '/', $root);

        if (strpos($forwardSlashedAppPath, $forwardSlashedDocumentRoot) !== 0) {
            $this->baseUrl = '';
            return;
        }

        $this->baseUrl = substr($forwardSlashedAppPath, strlen($root));
    }

    /**
     * @return bool|string
     */
    private function getRelativePath() {
        return substr($_SERVER['REQUEST_URI'], strlen($this->getBaseUrl()));
    }

    /**
     * @param array $callback
     * @param Annotation $parentTag
     * @param Annotation $tag
     * @param Closure[] $middleware
     * @throws Exception
     */
    private function mapControllerAction($callback, $parentTag, $tag, $middleware) {
        $path = $parentTag->args[0] ?? '';
        $parentMethod = $parentTag->args[1] ?? 'GET';
        $method = $tag->args[1] ?? $parentMethod;

        if (!empty($tag->args[0])) {
            $path .= '/'.$tag->args[0];
        }

        $this->map($path, $method, $callback, $middleware);
    }

    /**
     * @throws Exception
     */
    private function handlePreflight() {
        if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS')
            return;

        $origin = getallheaders()['Origin'] ?? '*';
        $allowed = ['OPTIONS'];
        $methods = ['HEAD', 'GET', 'POST', 'DELETE', 'PUT', 'PATCH'];

        $uri = $this->getRelativePath();

        foreach ($methods as $method) {
            $route = $this->findRoute($uri, $method);

            if ($route->getPath() === '404')
                continue;

            $allowed[] = $method;
        }

        $this->map('.*', 'OPTIONS', function () use ($origin, $allowed) {
            Header::send('Access-Control-Allow-Origin: '.$origin);
            Header::send('Access-Control-Allow-Methods: '.implode(', ', $allowed));
            Header::send("Access-Control-Allow-Headers: Content-Type, Authorization");
            Header::send('Access-Control-Max-Age: 86400');

            return '';
        });
    }
}
