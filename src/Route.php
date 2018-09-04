<?php
namespace Lipht\Mvc;

use Lipht\AnnotationReader;

class Route {
    private $pattern;
    private $method;
    private $callback;
    private $middleware = [];
    private $args = [];

    public function __construct(string $path, string $method, $callback, array $middleware = []) {
        if (!is_callable($callback))
            throw new \Exception('Callback must be callable');

        foreach ($middleware as $i => $filter) {
            if (!is_callable($filter))
                throw new \Exception('Middleware['.$i.'] filter must be callable');
        }

        [$this->pattern, $this->args] = $this->parsePathToPattern($path);
        $this->method = strtolower($method);
        $this->callback = $callback;
        $this->middleware = $middleware;
    }

    public function match(string $request, string $method) {
        $found = preg_match($this->pattern, $request);
        if ($found === false)
            throw new \Exception('Invalid pattern for route: '.$this->pattern);

        if (strtolower($method) !== $this->method)
            return false;

        return !!$found;
    }

    public function getMethod() {
        return $this->method;
    }

    public function invoke(string $request) {
        $last = $this->callback;
        $args = $this->parseArgs($request);
        $meta = $this->getMetaInfo();
        $annotation = AnnotationReader::parse($meta);

        if ($annotation) {
            $args->tags = $annotation->tags;
        }

        if (in_array($this->method, ['put', 'post', 'patch']))
            $args->payload = file_get_contents('php://input');

        $middleware = array_merge(
            array_reverse($this->middleware),
            [Middleware::result()]
        );

        foreach($middleware as $filter) {
            $last = function($args) use($filter, $last) {
                return $filter($last, $args);
            };
        }

        return call_user_func($last, $args);
    }

    public function parseArgs($request) {
        $matches = [];
        preg_match($this->pattern, $request, $matches);
        array_shift($matches);

        $args = new \stdClass();

        foreach($this->args as $key) {
            $args->{$key} = array_shift($matches);
        }

        return $args;
    }

    private function parsePathToPattern($path) {
        $parts = explode('/', rtrim($path, '/'));
        $pattern = [];
        $args = [];
        foreach ($parts as $part) {
            $pair = explode(':', $part, 2);

            if (!isset($pair[1])) {
                $pattern[] = $pair[0];
                continue;
            }

            $args[] = $pair[0];
            $pattern[] = '('.$pair[1].')';
        }

        return ['/^'.implode('\/', $pattern).'\/?$/i', $args];
    }

    private function getMetaInfo() {
        if (is_array($this->callback)) {
            return new \ReflectionMethod($this->callback[0], $this->callback[1]);
        }

        return new \ReflectionFunction($this->callback);
    }
}
