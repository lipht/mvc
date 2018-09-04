<?php
namespace Test\Helper\DummyDomain;

use Lipht\Mvc\Controller;
use Test\Helper\Dummy\DummyService;

/**
 * @route(dummy)
 */
class DummyController extends Controller {
    /**
     * @route()
     */
    public function index($args) {
        return "Hello World!";
    }

    /**
     * @route(,post)
     * @route(save, post)
     */
    public function submit($args) {
        return "Confirmed!";
    }

    /**
     * @route(number/id:\d+)
     */
    public function number($args, DummyService $service) {
        return $service->echo(__METHOD__.'.('.$args->id.')');
    }

    /**
     * @route(file)
     */
    public function file($args) {
        return "test/Helper/DummyDomain/View/file.txt.php";
    }

    /**
     * @route(hello, post)
     */
    public function hello($args) {
        return isset($args->payload);
    }

    /**
     * @Hello(World!)
     * @route(tagged)
     */
    public function annotations($args) {
        return $args->tags[0]->name.' '.$args->tags[0]->args[0];
    }
}
