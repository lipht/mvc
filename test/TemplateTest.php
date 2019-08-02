<?php
namespace Test;

use Lipht\Mvc\Template;

class TemplateTest extends TestCase
{
    public function testRenderWithFile()
    {
        $subject = new Template(__DIR__.'/Helper/DummyDomain/View/template.html.php');
        $subject->add([
            'ROWS' => [
                new Template(__DIR__.'/Helper/DummyDomain/View/template_row.html.php', null, ['NAME' => 'Alice']),
                new Template(__DIR__.'/Helper/DummyDomain/View/template_row.html.php', null, ['NAME' => 'Bob']),
            ],
        ]);

        $expected = <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta charset="UTF-8">
                </head>
                <body>
                    <table>
                        <tr><td>Alice</td></tr>
                        <tr><td>Bob</td></tr>
                    </table>
                </body>
            </html>
HTML;

        $expected = $this->trimLines($expected);
        $result = $this->trimLines($subject->render());

        $this->assertEquals($expected, $result);
        $this->assertEquals('text/html', $subject->getMime());
    }

    public function testRenderWithStrings()
    {
        $subject = new Template('<!DOCTYPE html><html><body>CHILD_DATA</body></html>', 'text/html');
        $subject->CHILD_DATA = new Template('<h1>TITLE</h1>', 'text/html', ['TITLE' => 'Alice in Wonderland']);

        $expected = <<<HTML
            <!DOCTYPE html><html><body><h1>Alice in Wonderland</h1></body></html>
HTML;

        $expected = $this->trimLines($expected);
        $result = $this->trimLines($subject->render());

        $this->assertEquals($expected, $result);
        $this->assertEquals('text/html', $subject->getMime());
    }

    private function trimLines($text) {
        return implode("\n", array_map('trim', array_filter(explode("\n", $text))));
    }
}
