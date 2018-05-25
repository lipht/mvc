<?php
namespace Test;

use Lipht\Mvc\Middleware;
use Lipht\Mvc\Router as OriginalRouter;

use Lipht\Module as BaseModule;
use Test\Helper\Dummy\DummyService;
use Test\Helper\DummyDomain\DummyController;

class RouterTest extends TestCase {
    public function setup() {
        parent::setup();

        $_SERVER['DOCUMENT_ROOT'] = dirname(dirname(__DIR__));
        $_SERVER['REQUEST_URI'] = '/mvc/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    public function testRootUrl() {
        $router = new Router(dirname(__DIR__));
        $this->assertEquals('/mvc/', $router->getBaseUrl());
    }

    public function testMiddleware() {
        $expected = [
            'echo' => 'uservalue',
            'mid1' => rand(0, 25),
            'mid2' => rand(0, 25),
            'mid3' => rand(0, 25),
        ];
        $_SERVER['REQUEST_URI'] = '/mvc/test/'.$expected['echo'].'/ok';
        $router = new Router(dirname(__DIR__));

        $middleware = [
            $this->setupMiddleware('mid1', $expected['mid1']),
            $this->setupMiddleware('mid2', $expected['mid2']),
            $this->setupMiddleware('mid3', $expected['mid3']),
        ];

        $self = $this;
        $router->map('test/echo:\w+/ok/', 'GET', function($args) use($self, $expected) {
            $self->assertEquals($expected, (array)$args,
                'Middleware failed to intercept');
        }, $middleware);
        $router->serve();
    }

    public function testCoreContainerAsMiddleware() {
        Module::init();
        $module = new Module();
        $router = new Router(dirname(__DIR__));

        $self = $this;
        $router->map('', 'GET', function($args, DummyService $service) use($self) {
            $expected = rand(1000, 9999);
            $self->assertEquals($expected, $service->echo($expected),
                'Service failed to be injected by middleware');
        }, [Middleware::module($module)]);
        $router->serve();
    }

    public function testMapController() {
        $router = new Router(dirname(__DIR__));
        $router->mapController(DummyController::class);

        $this->assertDummyRoute($router);
    }

    public function testMapControllerArray() {
        $router = new Router(dirname(__DIR__));
        $router->mapController([DummyController::class]);

        $this->assertDummyRoute($router);
    }

    public function testFileRouteOutput() {
        $router = new Router(dirname(__DIR__));
        $router->mapController(DummyController::class);

        ob_start();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/mvc/dummy/file';
        $router->serve();
        $result = ob_get_clean();

        $this->assertEquals("Hello World!\n", $result);
    }

    public function testJsonRouteOutput() {
        $router = new Router(dirname(__DIR__));
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

    private function assertDummyRoute($router) {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $route = $router->findRoute('dummy');

        $this->assertEquals('get', $route->getMethod());
        $this->assertEquals((object)[], $route->parseArgs('dummy'));

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $route = $router->findRoute('dummy/number/100');

        $this->assertEquals('get', $route->getMethod());
        $this->assertEquals(
            (object)['id' => '100'],
            $route->parseArgs('dummy/number/100')
        );

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $route = $router->findRoute('dummy');

        $this->assertEquals('post', $route->getMethod());
        $this->assertEquals((object)[], $route->parseArgs('dummy'));
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

class Module extends BaseModule {
    public static function listServices() {
        return [DummyService::class];
    }
}
class Router extends OriginalRouter {
    public function __construct($root) {
        $this->registerBaseDir($root);
    }
}
