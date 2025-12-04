<?php

declare(strict_types=1);

namespace ParkingSystem\Tests\Unit\Infrastructure\Http\Validation;

use ParkingSystem\Infrastructure\Http\Validation\SimpleValidator;
use PHPUnit\Framework\TestCase;

class SimpleValidatorTest extends TestCase
{
    private SimpleValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new SimpleValidator();
    }

    public function testValidDataReturnsNoErrors(): void
    {
        $data = ['email' => 'test@example.com', 'name' => 'John'];
        $rules = ['email' => ['required', 'email'], 'name' => ['required', 'string']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertEmpty($errors);
        $this->assertFalse($this->validator->hasErrors());
    }

    public function testRequiredRuleDetectsMissingField(): void
    {
        $data = [];
        $rules = ['email' => ['required']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertNotEmpty($errors);
        $this->assertTrue($this->validator->hasErrors());
        $this->assertArrayHasKey('email', $errors);
        $this->assertContains('This field is required', $errors['email']);
    }

    public function testRequiredRuleDetectsEmptyString(): void
    {
        $data = ['email' => ''];
        $rules = ['email' => ['required']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('email', $errors);
    }

    public function testEmailRuleValidatesCorrectEmail(): void
    {
        $data = ['email' => 'valid@example.com'];
        $rules = ['email' => ['email']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertEmpty($errors);
    }

    public function testEmailRuleRejectsInvalidEmail(): void
    {
        $data = ['email' => 'invalid-email'];
        $rules = ['email' => ['email']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('email', $errors);
        $this->assertContains('This field must be a valid email address', $errors['email']);
    }

    public function testEmailRuleSkipsEmptyValue(): void
    {
        $data = ['email' => ''];
        $rules = ['email' => ['email']];

        $errors = $this->validator->validate($data, $rules);

        // Email validation skips empty values (use 'required' separately)
        $this->assertEmpty($errors);
    }

    public function testStringRuleAcceptsString(): void
    {
        $data = ['name' => 'John Doe'];
        $rules = ['name' => ['string']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertEmpty($errors);
    }

    public function testStringRuleRejectsNonString(): void
    {
        $data = ['name' => 123];
        $rules = ['name' => ['string']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('name', $errors);
    }

    public function testIntegerRuleAcceptsInteger(): void
    {
        $data = ['age' => 25];
        $rules = ['age' => ['integer']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertEmpty($errors);
    }

    public function testIntegerRuleAcceptsNumericString(): void
    {
        $data = ['age' => '25'];
        $rules = ['age' => ['integer']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertEmpty($errors);
    }

    public function testIntegerRuleRejectsNonInteger(): void
    {
        $data = ['age' => '25.5'];
        $rules = ['age' => ['integer']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('age', $errors);
    }

    public function testNumericRuleAcceptsNumber(): void
    {
        $data = ['price' => 19.99];
        $rules = ['price' => ['numeric']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertEmpty($errors);
    }

    public function testNumericRuleAcceptsNumericString(): void
    {
        $data = ['price' => '19.99'];
        $rules = ['price' => ['numeric']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertEmpty($errors);
    }

    public function testNumericRuleRejectsNonNumeric(): void
    {
        $data = ['price' => 'not-a-number'];
        $rules = ['price' => ['numeric']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('price', $errors);
    }

    public function testMinRuleForString(): void
    {
        $data = ['password' => 'short'];
        $rules = ['password' => ['min:8']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('password', $errors);
        $this->assertContains('This field must be at least 8 characters', $errors['password']);
    }

    public function testMinRulePassesForValidString(): void
    {
        $data = ['password' => 'longenoughpassword'];
        $rules = ['password' => ['min:8']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertEmpty($errors);
    }

    public function testMinRuleForNumber(): void
    {
        $data = ['age' => 15];
        $rules = ['age' => ['min:18']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('age', $errors);
    }

    public function testMaxRuleForString(): void
    {
        $data = ['name' => 'This is a very long name that exceeds the maximum'];
        $rules = ['name' => ['max:10']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('name', $errors);
        $this->assertContains('This field must not exceed 10 characters', $errors['name']);
    }

    public function testMaxRulePassesForValidString(): void
    {
        $data = ['name' => 'Short'];
        $rules = ['name' => ['max:10']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertEmpty($errors);
    }

    public function testBetweenRuleForString(): void
    {
        $data = ['username' => 'ab'];
        $rules = ['username' => ['between:3,10']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('username', $errors);
    }

    public function testBetweenRulePassesForValidString(): void
    {
        $data = ['username' => 'john'];
        $rules = ['username' => ['between:3,10']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertEmpty($errors);
    }

    public function testBetweenRuleForNumber(): void
    {
        $data = ['age' => 150];
        $rules = ['age' => ['between:0,120']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('age', $errors);
    }

    public function testInRuleAcceptsValidValue(): void
    {
        $data = ['status' => 'active'];
        $rules = ['status' => ['in:active,inactive,pending']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertEmpty($errors);
    }

    public function testInRuleRejectsInvalidValue(): void
    {
        $data = ['status' => 'deleted'];
        $rules = ['status' => ['in:active,inactive,pending']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('status', $errors);
    }

    public function testMultipleRulesOnSameField(): void
    {
        $data = ['email' => 'invalid'];
        $rules = ['email' => ['required', 'email']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('email', $errors);
        $this->assertCount(1, $errors['email']); // Only email error
    }

    public function testMultipleFieldsValidation(): void
    {
        $data = [
            'email' => 'invalid-email',
            'name' => '',
            'age' => 'not-a-number'
        ];
        $rules = [
            'email' => ['required', 'email'],
            'name' => ['required', 'string'],
            'age' => ['required', 'integer']
        ];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('age', $errors);
    }

    public function testThrowsExceptionForUnknownRule(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown validation rule: unknown');

        $data = ['field' => 'value'];
        $rules = ['field' => ['unknown']];

        $this->validator->validate($data, $rules);
    }

    public function testGetErrorsReturnsAllErrors(): void
    {
        $data = ['email' => 'invalid'];
        $rules = ['email' => ['email']];

        $this->validator->validate($data, $rules);
        $errors = $this->validator->getErrors();

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testRequiredRuleDetectsEmptyArray(): void
    {
        $data = ['items' => []];
        $rules = ['items' => ['required']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertArrayHasKey('items', $errors);
    }

    public function testValidationResetsErrors(): void
    {
        // First validation with errors
        $data1 = ['email' => 'invalid'];
        $rules1 = ['email' => ['email']];
        $this->validator->validate($data1, $rules1);

        $this->assertTrue($this->validator->hasErrors());

        // Second validation without errors
        $data2 = ['email' => 'valid@example.com'];
        $rules2 = ['email' => ['email']];
        $this->validator->validate($data2, $rules2);

        $this->assertFalse($this->validator->hasErrors());
    }

    public function testMinRuleThrowsExceptionWithoutParameter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Min rule requires a parameter');

        // Try to trigger min validation without parameter
        $data = ['field' => 'value'];
        $rules = ['field' => ['min']];

        $this->validator->validate($data, $rules);
    }

    public function testStringRuleSkipsNullValue(): void
    {
        $data = ['name' => null];
        $rules = ['name' => ['string']];

        $errors = $this->validator->validate($data, $rules);

        $this->assertEmpty($errors);
    }
}
