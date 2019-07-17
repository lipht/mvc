<?php
namespace Test;

use Exception;
use Lipht\Annotation;
use Lipht\Mvc\Header;
use Lipht\Mvc\Middleware;
use Lipht\Mvc\Router as OriginalRouter;

use ReflectionException;
use Test\Helper\Dummy\Module;
use Test\Helper\Dummy\DummyInterface;
use Test\Helper\DummyDomain\DummyController;

class RouterTest extends TestCase {
    /**
     * @var string
     */
    private $root;

    public function setup() {
        parent::setup();

        $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
        $_SERVER['REQUEST_URI'] = '/mvc';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->root = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['REQUEST_URI'];
    }

    public function testRootUrl() {
        $router = new Router($this->root);
        $this->assertEquals('/mvc', $router->getBaseUrl());
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testMiddleware() {
        $expected = [
            'echo' => 'uservalue',
            'tags' => [],
            'mid1' => rand(0, 25),
            'mid2' => rand(0, 25),
            'mid3' => rand(0, 25),
        ];
        $_SERVER['REQUEST_URI'] = '/mvc/test/'.$expected['echo'].'/ok';
        $router = new Router($this->root);

        $middleware = [
            $this->setupMiddleware('mid1', $expected['mid1']),
            $this->setupMiddleware('mid2', $expected['mid2']),
            $this->setupMiddleware('mid3', $expected['mid3']),
        ];

        $router->map('test/echo:\w+/ok/', 'GET', function($args) use($expected) {
            $this->assertEquals($expected, (array)$args,
                'Middleware failed to intercept');
        }, $middleware);
        $router->serve();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testCoreContainerAsMiddleware() {
        Module::init();
        $module = Module::getInstance();
        $router = new Router($this->root);

        $router->map('', 'GET', function($args, DummyInterface $service) {
            $expected = rand(1000, 9999);
            $this->assertEquals($expected, $service->echo($expected),
                'Service failed to be injected by middleware');
        }, [Middleware::module($module)]);
        $router->serve();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testMapController() {
        $router = new Router($this->root);
        $router->mapController(DummyController::class);

        $this->assertDummyRoute($router);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testMapControllerArray() {
        $router = new Router($this->root);
        $router->mapController([DummyController::class]);

        $this->assertDummyRoute($router);
    }

    /**
     * @throws ReflectionException
     */
    public function testFileRouteOutput() {
        $router = new Router($this->root);
        $router->mapController(DummyController::class);

        ob_start();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/mvc/dummy/file';
        $router->serve();
        $result = ob_get_clean();

        $this->assertEquals("Hello World!\n", $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testAnnotationArg() {
        $annotationMiddleware = function($callback, $args) {
            $this->assertIsArray($args->tags);
            /** @var Annotation $custom */
            /** @var Annotation $route */
            [$custom, $route] = $args->tags;

            $this->assertInstanceOf(Annotation::class, $custom);
            $this->assertEquals('Hello', $custom->name);
            $this->assertCount(1, $custom->args);
            $this->assertEquals('World!', reset($custom->args));

            $this->assertInstanceOf(Annotation::class, $route);
            $this->assertEquals('route', $route->name);
            $this->assertCount(1, $route->args);
            $this->assertEquals('tagged', reset($route->args));

            return call_user_func($callback, $args);
        };

        $router = new Router($this->root);
        $router->mapController(DummyController::class, [
            $annotationMiddleware
        ]);

        ob_start();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/mvc/dummy/tagged';
        $router->serve();
        $result = ob_get_clean();

        $this->assertEquals("Hello World!", $result);
    }

    /**
     * @runInSeparateProcess
     * @throws ReflectionException
     * @throws Exception
     */
    public function testJsonRouteOutput() {
        $router = new Router($this->root);
        $expected = (object)[
            'something' => 'clever',
        ];

        $router->map('test/json', 'GET', function($args) use($expected){
            return $expected;
        });

        ob_start();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/mvc/test/json';
        $router->serve();
        $result = ob_get_clean();

        $this->assertEquals(json_encode($expected), $result);
    }

    /**
     * @param OriginalRouter $router
     * @throws Exception
     */
    private function assertDummyRoute($router) {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $route = $router->findRoute('dummy');

        $this->assertEquals('get', $route->getMethod());
        $this->assertEquals((object)[], $route->parseArgs('dummy'));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $route = $router->findRoute('/dummy/number/100');

        $this->assertEquals('get', $route->getMethod());
        $this->assertEquals(
            (object)['id' => '100'],
            $route->parseArgs('/dummy/number/100')
        );

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $route = $router->findRoute('dummy');

        $this->assertEquals('post', $route->getMethod());
        $this->assertEquals((object)[], $route->parseArgs('dummy'));
    }

    /**
     * getallheaders() is exclusive of the apache extension, so there is a polyfill if necessary
     */
    public function testGetHeaders()
    {
        $_SERVER['HTTP_CUSTOM_HEADER'] = 'custom-value';
        $expected = [ 'Custom-Header' => 'custom-value' ];

        $result = getallheaders();

        $this->assertEquals($expected, $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testPreFlight()
    {
        $router = new Router($this->root);
        $router->mapController(DummyController::class);

        ob_start();
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['REQUEST_URI'] = '/mvc/dummy';
        $router->serve();
        $result = ob_get_clean();
        $resultHeaders = Header::getCliHeaders();

        $expectedHeaders = [
            'HTTP/1.1 200 OK',
            'Access-Control-Allow-Origin: *',
            'Access-Control-Allow-Methods: OPTIONS, GET, POST',
            'Access-Control-Allow-Headers: Content-Type, Authorization',
            'Access-Control-Max-Age: 86400',
        ];

        $this->assertEquals('', $result);
        $this->assertEquals($expectedHeaders, $resultHeaders);
    }

    private function setupMiddleware($key, $value) {
        return function($callback, $args) use($key, $value){
            $args->{$key} = $value;
            return call_user_func(
                $callback,
                $args
            );
        };
    }
}

class Router extends OriginalRouter {
    public function __construct($root) {
        $this->registerBaseDir($root, $_SERVER['DOCUMENT_ROOT']);
    }
}
