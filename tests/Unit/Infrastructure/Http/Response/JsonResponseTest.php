<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\Infrastructure\Http\Response;

use ParkingSystem\Infrastructure\Http\Response\JsonResponse;
use PHPUnit\Framework\TestCase;

class JsonResponseTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $data = ['id' => 123, 'name' => 'Test'];
        $response = new JsonResponse($data, 200);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($data, $response->getBody());
    }

    public function testDefaultStatusCodeIs200(): void
    {
        $response = new JsonResponse([]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testContentTypeHeaderIsSet(): void
    {
        $response = new JsonResponse([]);
        $headers = $response->getHeaders();

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertEquals('application/json', $headers['Content-Type']);
    }

    public function testAdditionalHeadersAreMerged(): void
    {
        $response = new JsonResponse([], 200, ['X-Custom-Header' => 'value']);
        $headers = $response->getHeaders();

        $this->assertArrayHasKey('X-Custom-Header', $headers);
        $this->assertEquals('value', $headers['X-Custom-Header']);
    }

    public function testToJsonEncodesData(): void
    {
        $data = ['id' => 123, 'name' => 'Test'];
        $response = new JsonResponse($data);

        $json = $response->toJson();

        $this->assertEquals(json_encode($data), $json);
        $this->assertJson($json);
    }

    public function testToJsonHandlesUnicodeCorrectly(): void
    {
        $data = ['message' => 'Hello 世界 🌍'];
        $response = new JsonResponse($data);

        $json = $response->toJson();

        $this->assertStringContainsString('世界', $json);
        $this->assertStringContainsString('🌍', $json);
    }

    public function testSuccessCreatesSuccessResponse(): void
    {
        $data = ['id' => 123];
        $response = JsonResponse::success($data, 'Operation successful');

        $this->assertEquals(200, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertTrue($body['success']);
        $this->assertEquals('Operation successful', $body['message']);
        $this->assertEquals($data, $body['data']);
    }

    public function testSuccessWithCustomStatusCode(): void
    {
        $response = JsonResponse::success(null, 'OK', 202);

        $this->assertEquals(202, $response->getStatusCode());
    }

    public function testErrorCreatesErrorResponse(): void
    {
        $response = JsonResponse::error('Something went wrong', null, 400);

        $this->assertEquals(400, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertFalse($body['success']);
        $this->assertEquals('Something went wrong', $body['message']);
    }

    public function testErrorWithValidationErrors(): void
    {
        $errors = ['email' => ['Email is invalid']];
        $response = JsonResponse::error('Validation failed', $errors);

        $body = $response->getBody();
        $this->assertArrayHasKey('errors', $body);
        $this->assertEquals($errors, $body['errors']);
    }

    public function testCreatedReturns201(): void
    {
        $data = ['id' => 123];
        $response = JsonResponse::created($data);

        $this->assertEquals(201, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertTrue($body['success']);
        $this->assertEquals('Resource created', $body['message']);
    }

    public function testNoContentReturns204(): void
    {
        $response = JsonResponse::noContent();

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals([], $response->getBody());
    }

    public function testNotFoundReturns404(): void
    {
        $response = JsonResponse::notFound('User not found');

        $this->assertEquals(404, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertFalse($body['success']);
        $this->assertEquals('User not found', $body['message']);
    }

    public function testUnauthorizedReturns401(): void
    {
        $response = JsonResponse::unauthorized();

        $this->assertEquals(401, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertFalse($body['success']);
        $this->assertEquals('Unauthorized', $body['message']);
    }

    public function testForbiddenReturns403(): void
    {
        $response = JsonResponse::forbidden('Access denied');

        $this->assertEquals(403, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertEquals('Access denied', $body['message']);
    }

    public function testValidationErrorReturns422(): void
    {
        $errors = [
            'email' => ['Email is required', 'Email is invalid'],
            'password' => ['Password is too short']
        ];
        $response = JsonResponse::validationError($errors);

        $this->assertEquals(422, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertFalse($body['success']);
        $this->assertEquals('Validation failed', $body['message']);
        $this->assertEquals($errors, $body['errors']);
    }

    public function testServerErrorReturns500(): void
    {
        $response = JsonResponse::serverError();

        $this->assertEquals(500, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertFalse($body['success']);
        $this->assertEquals('Internal server error', $body['message']);
    }

    public function testSuccessWithNullData(): void
    {
        $response = JsonResponse::success(null, 'No content');

        $body = $response->getBody();
        $this->assertNull($body['data']);
    }

    public function testCreatedWithCustomMessage(): void
    {
        $data = ['id' => 456];
        $response = JsonResponse::created($data, 'User created successfully');

        $body = $response->getBody();
        $this->assertEquals('User created successfully', $body['message']);
    }

    public function testNotFoundWithDefaultMessage(): void
    {
        $response = JsonResponse::notFound();

        $body = $response->getBody();
        $this->assertEquals('Resource not found', $body['message']);
    }
}
