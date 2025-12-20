<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\Infrastructure\Http\Request;

use ParkingSystem\Infrastructure\Http\Request\HttpRequest;
use PHPUnit\Framework\TestCase;

class HttpRequestTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $request = new HttpRequest(
            'POST',
            '/api/users',
            ['Content-Type' => 'application/json'],
            ['email' => 'test@example.com'],
            ['page' => '1']
        );

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/api/users', $request->getPath());
        $this->assertEquals(['email' => 'test@example.com'], $request->getBody());
        $this->assertEquals(['page' => '1'], $request->getQueryParams());
    }

    public function testMethodIsUppercased(): void
    {
        $request = new HttpRequest('post', '/api/users');

        $this->assertEquals('POST', $request->getMethod());
    }

    public function testGetHeaderCaseInsensitive(): void
    {
        $request = new HttpRequest(
            'GET',
            '/api/users',
            ['Content-Type' => 'application/json', 'Authorization' => 'Bearer token']
        );

        $this->assertEquals('application/json', $request->getHeader('content-type'));
        $this->assertEquals('application/json', $request->getHeader('Content-Type'));
        $this->assertEquals('application/json', $request->getHeader('CONTENT-TYPE'));
        $this->assertEquals('Bearer token', $request->getHeader('authorization'));
    }

    public function testGetHeaderReturnsNullWhenNotFound(): void
    {
        $request = new HttpRequest('GET', '/api/users');

        $this->assertNull($request->getHeader('x-custom-header'));
    }

    public function testGetAllHeaders(): void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer token'
        ];

        $request = new HttpRequest('GET', '/api/users', $headers);
        $allHeaders = $request->getHeaders();

        // Headers sont normalisés en lowercase
        $this->assertArrayHasKey('content-type', $allHeaders);
        $this->assertArrayHasKey('authorization', $allHeaders);
        $this->assertEquals('application/json', $allHeaders['content-type']);
    }

    public function testGetBodyReturnsNull(): void
    {
        $request = new HttpRequest('GET', '/api/users');

        $this->assertNull($request->getBody());
    }

    public function testGetQueryParam(): void
    {
        $request = new HttpRequest(
            'GET',
            '/api/users',
            [],
            null,
            ['page' => '2', 'limit' => '10']
        );

        $this->assertEquals('2', $request->getQueryParam('page'));
        $this->assertEquals('10', $request->getQueryParam('limit'));
        $this->assertNull($request->getQueryParam('nonexistent'));
    }

    public function testGetQueryParams(): void
    {
        $queryParams = ['page' => '1', 'sort' => 'name'];
        $request = new HttpRequest('GET', '/api/users', [], null, $queryParams);

        $this->assertEquals($queryParams, $request->getQueryParams());
    }

    public function testPathParamsAreEmptyByDefault(): void
    {
        $request = new HttpRequest('GET', '/api/users');

        $this->assertEquals([], $request->getPathParams());
        $this->assertNull($request->getPathParam('id'));
    }

    public function testSetAndGetPathParams(): void
    {
        $request = new HttpRequest('GET', '/api/users/123');
        $request->setPathParams(['id' => '123']);

        $this->assertEquals(['id' => '123'], $request->getPathParams());
        $this->assertEquals('123', $request->getPathParam('id'));
    }

    public function testGetContentType(): void
    {
        $request = new HttpRequest(
            'POST',
            '/api/users',
            ['Content-Type' => 'application/json']
        );

        $this->assertEquals('application/json', $request->getContentType());
    }

    public function testGetContentTypeReturnsNullWhenNotSet(): void
    {
        $request = new HttpRequest('GET', '/api/users');

        $this->assertNull($request->getContentType());
    }

    public function testIsJsonReturnsTrueForJsonContentType(): void
    {
        $request = new HttpRequest(
            'POST',
            '/api/users',
            ['Content-Type' => 'application/json']
        );

        $this->assertTrue($request->isJson());
    }

    public function testIsJsonReturnsTrueForJsonContentTypeWithCharset(): void
    {
        $request = new HttpRequest(
            'POST',
            '/api/users',
            ['Content-Type' => 'application/json; charset=utf-8']
        );

        $this->assertTrue($request->isJson());
    }

    public function testIsJsonReturnsFalseForNonJsonContentType(): void
    {
        $request = new HttpRequest(
            'POST',
            '/api/users',
            ['Content-Type' => 'text/html']
        );

        $this->assertFalse($request->isJson());
    }

    public function testIsJsonReturnsFalseWhenNoContentType(): void
    {
        $request = new HttpRequest('POST', '/api/users');

        $this->assertFalse($request->isJson());
    }
}
