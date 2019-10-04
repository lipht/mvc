<?php
namespace Lipht\Mvc;

use Throwable;

abstract class Controller {
    /**
     * @var Router $router
     */
    protected $router;

    /**
     * Controller constructor.
     * @param Router $router
     */
    public function __construct(Router $router) {
        $this->router = $router;
    }

    /**
     * @param string $path
     */
    protected function redirect($path) {
        Header::send('Location: '.$path);
        exit;
    }

    /**
     * @return false|string
     */
    protected function readRaw() {
        return file_get_contents('php://input');
    }

    /**
     * @return mixed
     */
    protected function readJson() {
        return json_decode($this->readRaw());
    }

    /**
     * @param object $payload
     * @param array $inputList
     * @throws PayloadParseException
     * @throws Throwable
     */
    protected function requireInput($payload, $inputList)
    {
        $this->acceptInput($payload, $inputList, $required = true);
    }

    /**
     * @param object $payload
     * @param array $inputList
     * @param bool $required
     * @throws PayloadParseException
     * @throws Throwable
     */
    protected function acceptInput($payload, $inputList, $required = false)
    {
        if (is_null($payload)) {
            $this->throwOrNot(new PayloadParseException('MISSING_PAYLOAD'), $required);
            return;
        }

        foreach ($inputList as $path => $types) {
            if (!is_array($types)) {
                $types = [$types];
            }

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

                if (!in_array(gettype($checkObj), $types)) {
                    throw new PayloadParseException('UNEXPECTED_TYPE', ['key' => implode('.', $check), 'expected' => implode('|', $types), 'type' => gettype($checkObj)]);
                }
            }
        }
    }

    /**
     * @param Throwable $exception
     * @param bool $throw
     * @throws Throwable
     */
    private function throwOrNot(Throwable $exception, $throw = true)
    {
        if (!$throw) {
            return;
        }

        throw $exception;
    }
}
