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

    protected function requireInput($payload, $inputList)
    {
        $this->acceptInput($payload, $inputList, $required = true);
    }

    protected function acceptInput($payload, $inputList, $required = false)
    {
        if (is_null($payload)) {
            $this->throwOrNot(new PayloadParseException('MISSING_PAYLOAD'), $required);
            return;
        }

        foreach ($inputList as $path => $type) {
            $parts = explode('.', $path);
            $check = [];
            $checkObj = $payload;
            foreach ($parts as $part) {
                $check[] = $part;
                $checkObj = $checkObj->{$part} ?? null;

                if (is_null($checkObj)) {
                    $this->throwOrNot(new PayloadParseException('MISSING_FIELD', ['key' => implode('.', $check)]), $required);
                    return;
                }

                if (!empty(array_diff($parts, $check))) {
                    continue;
                }

                if (gettype($checkObj) != $type) {
                    throw new PayloadParseException('UNEXPECTED_TYPE', ['key' => implode('.', $check), 'expected' => $type, 'type' => gettype($checkObj)]);
                }
            }
        }
    }

    private function throwOrNot($exception, $throw = true)
    {
        if (!$throw) {
            return;
        }

        throw $exception;
    }
}
