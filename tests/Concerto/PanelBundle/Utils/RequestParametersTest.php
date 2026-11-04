<?php

namespace Tests\Concerto\PanelBundle\Utils;

use Concerto\PanelBundle\Utils\RequestParameters;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RequestParametersTest extends TestCase
{
    private function request(array $query, array $body, array $attributes = array())
    {
        return new Request($query, $body, $attributes);
    }

    public function testLookupOrderIsAttributesQueryBody()
    {
        $request = $this->request(array('a' => 'query', 'b' => 'query'), array('a' => 'body', 'b' => 'body', 'c' => 'body'), array('a' => 'attribute'));

        $this->assertSame('attribute', RequestParameters::get($request, 'a'));
        $this->assertSame('query', RequestParameters::get($request, 'b'));
        $this->assertSame('body', RequestParameters::get($request, 'c'));
    }

    public function testArrayValuesAreReturned()
    {
        $request = $this->request(array('q' => array('x' => '1')), array('values' => array('temp' => 'v', 'n' => array(1, 2))));

        $this->assertSame(array('x' => '1'), RequestParameters::get($request, 'q'));
        $this->assertSame(array('temp' => 'v', 'n' => array(1, 2)), RequestParameters::get($request, 'values'));
    }

    public function testDefaultOnlyWhenMissing()
    {
        $request = $this->request(array('empty' => ''), array('nil' => null), array('attrNull' => null));

        $this->assertSame('fallback', RequestParameters::get($request, 'missing', 'fallback'));
        $this->assertNull(RequestParameters::get($request, 'missing'));
        $this->assertSame('', RequestParameters::get($request, 'empty', 'fallback'));
        $this->assertNull(RequestParameters::get($request, 'nil', 'fallback'));
        $this->assertNull(RequestParameters::get($request, 'attrNull', 'fallback'));
    }

    /**
     * Same results as the Request::get() it replaces, for every key of a mixed request.
     *
     * @group legacy
     */
    public function testMatchesRequestGet()
    {
        if (!method_exists(Request::class, 'get')) {
            $this->markTestSkipped('Request::get() no longer exists');
        }
        $request = $this->request(
            array('a' => 'q', 'arr' => array(1), 'e' => ''),
            array('a' => 'b', 'b' => 'b', 'arr' => 'b', 'nil' => null),
            array('b' => 'attr', 'n' => null)
        );
        foreach (array('a', 'b', 'arr', 'e', 'nil', 'n', 'missing') as $key) {
            $this->assertSame($request->get($key, 'dflt'), RequestParameters::get($request, $key, 'dflt'), $key);
        }
    }
}
