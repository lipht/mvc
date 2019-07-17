<?php
namespace Test;

use Lipht\Mvc\Header;

class HeaderTest extends TestCase {
    public function testCliHeaders() {
        $headers = [
            'Custom-Header: custom-value'
        ];

        foreach ($headers as $header) {
            Header::send($header);
        }

        $result = Header::getCliHeaders();

        $this->assertEquals($headers, $result);
    }
}
