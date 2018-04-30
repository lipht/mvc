<?php
namespace Lipht\Mvc;

use Lipht\AnnotationReader;

class Router {
    private $baseUrl = null;
    private $routes = [];

    public function __construct($root) {
        register_shutdown_function([$this, 'serve']);
        $this->registerBaseDir($root);
    }

    public function map($path, $method, $callback, $middleware = []) {
        $this->routes[] = new Route($path, $method, $callback, $middleware);
    }

    public function mapController($className, $middleware = []) {
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
        $uri = $this->getRelativePath();
        $route = $this->findRoute($uri);
        $route->invoke($uri);
    }

    public function findRoute($path) {
        $method = $_SERVER['REQUEST_METHOD'];
        foreach ($this->routes as $route) {
            if ($route->match($path, $method))
                return $route;
        }

        return new Route($path, $method, function($args) { header('HTTP/1.1 404 Not Found'); });
    }

    protected function registerBaseDir($path) {
        $forwardSlashedDocumentRoot = str_replace('\\', '/', $_SERVER["DOCUMENT_ROOT"]);
        $this->baseUrl = substr($path, strlen($forwardSlashedDocumentRoot)).'/';
    }

    private function getRelativePath() {
        return substr($_SERVER['REQUEST_URI'], strlen($this->getBaseUrl()));
    }

    private function mapControllerAction($callback, $parentTag, $tag, $middleware) {
        $path = $parentTag->args[0];
        $parentMethod = isset($parentTag->args[1]) ? $parentTag->args[1] : 'GET';
        $method = isset($tag->args[1]) ? $tag->args[1] : $parentMethod;

        if (!empty($tag->args[0])) {
            $path .= '/'.$tag->args[0];
        }

        $this->map($path, $method, $callback, $middleware);
    }
}
