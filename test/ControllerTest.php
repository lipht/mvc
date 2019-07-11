<?php
namespace Test;

use Lipht\Mvc\Controller;

class ControllerTest extends TestCase
{
    public function testRequiredParse()
    {
        $subject = new ControllerForTests();
        $subject->requiredParse(json_decode(json_encode([
            'foo' => [
                'bar' => [
                    'baz' => 200
                ]
            ]
        ])));

        $this->assertTrue(true);
    }

    /**
     * @expectedException Lipht\Mvc\PayloadParseException
     * @expectedExceptionMessage UNEXPECTED_TYPE
     */
    public function testRequiredParseTypeError()
    {
        $subject = new ControllerForTests();
        $subject->requiredParse(json_decode(json_encode([
            'foo' => [
                'bar' => [
                    'baz' => 'err'
                ]
            ]
        ])));
    }

    /**
     * @expectedException Lipht\Mvc\PayloadParseException
     * @expectedExceptionMessage MISSING_FIELD
     */
    public function testRequiredParseMissingNode()
    {
        $subject = new ControllerForTests();
        $subject->requiredParse(json_decode(json_encode([
            'foo' => [
                'bar' => []
            ]
        ])));
    }

    /**
     * @expectedException Lipht\Mvc\PayloadParseException
     * @expectedExceptionMessage MISSING_PAYLOAD
     */
    public function testRequiredParseNull()
    {
        $subject = new ControllerForTests();
        $subject->requiredParse(null);
    }

    public function testAcceptedParse()
    {
        $subject = new ControllerForTests();
        $subject->acceptedParse(json_decode(json_encode([
            'foo' => [
                'bar' => [
                    'baz' => 200
                ]
            ]
        ])));

        $this->assertTrue(true);
    }

    /**
     * @expectedException Lipht\Mvc\PayloadParseException
     * @expectedExceptionMessage UNEXPECTED_TYPE
     */
    public function testAcceptedParseTypeError()
    {
        $subject = new ControllerForTests();
        $subject->acceptedParse(json_decode(json_encode([
            'foo' => [
                'bar' => [
                    'baz' => 'err'
                ]
            ]
        ])));
    }

    public function testAcceptedParseMissingNode()
    {
        $subject = new ControllerForTests();
        $subject->acceptedParse(json_decode(json_encode([
            'foo' => [
                'bar' => []
            ]
        ])));
        $this->assertTrue(true);
    }

    public function testAcceptedParseNull()
    {
        $subject = new ControllerForTests();
        $subject->acceptedParse(null);
        $this->assertTrue(true);
    }
}

class ControllerForTests extends Controller
{
    public function __construct() {}

    public function requiredParse($payload)
    {
        $this->requireInput($payload, [
            'foo.bar.baz' => 'integer',
        ]);
    }

    public function acceptedParse($payload)
    {
        $this->acceptInput($payload, [
            'foo.bar.baz' => 'integer',
        ]);
    }
}