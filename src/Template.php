<?php
namespace Lipht\Mvc;

class Template
{
    private $mime;
    private $contents;
    private $viewbag;

    public function __construct($file, $mime = null, $viewbag = [])
    {
        $this->mime = $mime;
        $this->contents = $file;
        $this->viewbag = $viewbag;

        if (file_exists($file)) {
            $this->contents = file_get_contents($file);

            if (!$mime) {
                $this->mime = mime_content_type($file);
            }
        }
    }

    public function __set($name, $value)
    {
        $this->viewbag[$name] = $value;
    }

    public function __get($name)
    {
        return $this->viewbag[$name] ?? null;
    }

    public function add(array $viewbag) {
        $this->viewbag = array_replace_recursive($this->viewbag, $viewbag);
    }

    public function getMime()
    {
        return $this->mime;
    }

    public function render(array $viewbag = [])
    {
        $viewbag = $this->sortViewbag(array_replace_recursive($this->viewbag, $viewbag));
        $contents = $this->contents;

        foreach ($viewbag as $token => $value) {
            $value = $this->renderTag($value);
            $contents = str_replace('%'.$token.'%', $value, $contents);
        }

        return $contents;
    }

    private function renderTag($value)
    {
        if (is_array($value)) {
            return implode('', array_map([$this, 'renderTag'], $value));
        }

        if (is_a($value, self::class)) {
            return $value->render();
        }

        return $value;
    }

    private function sortViewbag($viewbag)
    {
        uksort($viewbag, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        return $viewbag;
    }
}
