<?php
namespace Lipht\Mvc;

use Lipht\AnnotationReader;

class Router {
    private $baseUrl = null;
    private $routes = [];

    public function __construct($appRoot, $docRoot = null) {
        register_shutdown_function([$this, 'serve']);

        $docRoot = $docRoot ?? $_SERVER['DOCUMENT_ROOT'];

        $this->registerBaseDir($appRoot, $docRoot);
    }

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

    public function mapController($className, $middleware = []) {
        if (is_array($className)) {
            foreach ($className as $class) {
                $this->mapController($class, $middleware);
            }
            return;
        }

        $TAG_NAME = 'route';

        $instance = new $className($this);
        $meta = new \ReflectionClass($className);
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

    public function getBaseUrl() {
        return $this->baseUrl;
    }

    public function serve() {
        $this->handlePreflight();

        $uri = $this->getRelativePath();
        $route = $this->findRoute($uri);
        $route->invoke($uri);
    }

    public function findRoute($path, $method = null) {
        $method = $method ?? $_SERVER['REQUEST_METHOD'];
        foreach ($this->routes as $route) {
            if ($route->match($path, $method))
                return $route;
        }

        return new Route('404', $method, function($args) { header('HTTP/1.1 404 Not Found'); });
    }

    protected function registerBaseDir($path, $root) {
        $forwardSlashedAppPath = str_replace('\\', '/', $path);
        $forwardSlashedDocumentRoot = str_replace('\\', '/', $root);

        if (strpos($forwardSlashedAppPath, $forwardSlashedDocumentRoot) !== 0) {
            $this->baseUrl = '';
            return;
        }

        $this->baseUrl = substr($forwardSlashedAppPath, strlen($root));
    }

    private function getRelativePath() {
        return substr($_SERVER['REQUEST_URI'], strlen($this->getBaseUrl()));
    }

    private function mapControllerAction($callback, $parentTag, $tag, $middleware) {
        $path = $parentTag->args[0] ?? '';
        $parentMethod = $parentTag->args[1] ?? 'GET';
        $method = $tag->args[1] ?? $parentMethod;

        if (!empty($tag->args[0])) {
            $path .= '/'.$tag->args[0];
        }

        $this->map($path, $method, $callback, $middleware);
    }

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

        $this->map('.*', 'OPTIONS', function ($args) use ($origin, $allowed) {
            header('Access-Control-Allow-Origin: '.$origin);
            header('Access-Control-Allow-Methods: '.implode(', ', $allowed));
            header("Access-Control-Allow-Headers: Content-Type, Authorization");
            header('Access-Control-Max-Age: 86400');

            return '';
        });
    }
}
