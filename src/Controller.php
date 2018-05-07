<?php
namespace Lipht\Mvc;

abstract class Controller {
    protected $router;

    public function __construct(Router $router) {
        $this->router = $router;
    }

    protected function redirect($path) {
        header('Location: '.$path);
        exit;
    }

    protected function readRaw() {
        return file_get_contents('php://input');
    }

    protected function readJson() {
        return json_decode($this->readRaw());
    }
}
