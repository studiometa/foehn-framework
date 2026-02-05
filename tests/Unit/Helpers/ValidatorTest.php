<?php

declare(strict_types=1);

use Studiometa\Foehn\Helpers\ValidationException;
use Studiometa\Foehn\Helpers\Validator;

describe('Validator', function () {
    describe('required rule', function () {
        it('fails when field is missing', function () {
            $validator = Validator::make([], ['name' => 'required']);

            expect($validator->fails())->toBeTrue();
            expect($validator->firstError('name'))->toContain('required');
        });

        it('fails when field is empty string', function () {
            $validator = Validator::make(['name' => ''], ['name' => 'required']);

            expect($validator->fails())->toBeTrue();
        });

        it('fails when field is null', function () {
            $validator = Validator::make(['name' => null], ['name' => 'required']);

            expect($validator->fails())->toBeTrue();
        });

        it('passes when field has value', function () {
            $validator = Validator::make(['name' => 'John'], ['name' => 'required']);

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('email rule', function () {
        it('fails for invalid email', function () {
            $validator = Validator::make(['email' => 'invalid'], ['email' => 'email']);

            expect($validator->fails())->toBeTrue();
            expect($validator->firstError('email'))->toContain('valid email');
        });

        it('passes for valid email', function () {
            $validator = Validator::make(['email' => 'test@example.com'], ['email' => 'email']);

            expect($validator->passes())->toBeTrue();
        });

        it('passes for empty optional email', function () {
            $validator = Validator::make(['email' => ''], ['email' => 'email']);

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('url rule', function () {
        it('fails for invalid URL', function () {
            $validator = Validator::make(['website' => 'not-a-url'], ['website' => 'url']);

            expect($validator->fails())->toBeTrue();
        });

        it('passes for valid URL', function () {
            $validator = Validator::make(['website' => 'https://example.com'], ['website' => 'url']);

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('numeric rule', function () {
        it('fails for non-numeric value', function () {
            $validator = Validator::make(['age' => 'abc'], ['age' => 'numeric']);

            expect($validator->fails())->toBeTrue();
        });

        it('passes for integer', function () {
            $validator = Validator::make(['age' => 25], ['age' => 'numeric']);

            expect($validator->passes())->toBeTrue();
        });

        it('passes for float', function () {
            $validator = Validator::make(['price' => 19.99], ['price' => 'numeric']);

            expect($validator->passes())->toBeTrue();
        });

        it('passes for numeric string', function () {
            $validator = Validator::make(['age' => '25'], ['age' => 'numeric']);

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('integer rule', function () {
        it('fails for float', function () {
            $validator = Validator::make(['count' => 1.5], ['count' => 'integer']);

            expect($validator->fails())->toBeTrue();
        });

        it('passes for integer', function () {
            $validator = Validator::make(['count' => 10], ['count' => 'integer']);

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('min rule', function () {
        it('fails when string is too short', function () {
            $validator = Validator::make(['name' => 'ab'], ['name' => 'min:3']);

            expect($validator->fails())->toBeTrue();
            expect($validator->firstError('name'))->toContain('at least 3 characters');
        });

        it('passes when string is long enough', function () {
            $validator = Validator::make(['name' => 'abc'], ['name' => 'min:3']);

            expect($validator->passes())->toBeTrue();
        });

        it('fails when number is too small', function () {
            $validator = Validator::make(['age' => 15], ['age' => 'numeric|min:18']);

            expect($validator->fails())->toBeTrue();
            expect($validator->firstError('age'))->toContain('at least 18');
        });

        it('passes when number is large enough', function () {
            $validator = Validator::make(['age' => 21], ['age' => 'numeric|min:18']);

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('max rule', function () {
        it('fails when string is too long', function () {
            $validator = Validator::make(['name' => 'abcdef'], ['name' => 'max:5']);

            expect($validator->fails())->toBeTrue();
            expect($validator->firstError('name'))->toContain('not exceed 5 characters');
        });

        it('passes when string is short enough', function () {
            $validator = Validator::make(['name' => 'abc'], ['name' => 'max:5']);

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('between rule', function () {
        it('fails when value is outside range', function () {
            $validator = Validator::make(['age' => 10], ['age' => 'numeric|between:18,65']);

            expect($validator->fails())->toBeTrue();
            expect($validator->firstError('age'))->toContain('between 18 and 65');
        });

        it('passes when value is within range', function () {
            $validator = Validator::make(['age' => 30], ['age' => 'numeric|between:18,65']);

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('in rule', function () {
        it('fails when value not in list', function () {
            $validator = Validator::make(['status' => 'unknown'], ['status' => 'in:draft,published,archived']);

            expect($validator->fails())->toBeTrue();
            expect($validator->firstError('status'))->toContain('one of');
        });

        it('passes when value is in list', function () {
            $validator = Validator::make(['status' => 'published'], ['status' => 'in:draft,published,archived']);

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('confirmed rule', function () {
        it('fails when confirmation does not match', function () {
            $validator = Validator::make([
                'password' => 'secret',
                'password_confirmation' => 'different',
            ], ['password' => 'confirmed']);

            expect($validator->fails())->toBeTrue();
            expect($validator->firstError('password'))->toContain('confirmation does not match');
        });

        it('passes when confirmation matches', function () {
            $validator = Validator::make([
                'password' => 'secret',
                'password_confirmation' => 'secret',
            ], ['password' => 'confirmed']);

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('multiple rules', function () {
        it('validates multiple rules on single field', function () {
            $validator = Validator::make(['email' => 'x'], ['email' => 'required|email|min:5']);

            expect($validator->fails())->toBeTrue();
            // Should have both email and min errors
            expect($validator->errors()['email'])->toHaveCount(2);
        });

        it('validates multiple fields', function () {
            $validator = Validator::make([
                'name' => '',
                'email' => 'invalid',
            ], [
                'name' => 'required',
                'email' => 'required|email',
            ]);

            expect($validator->fails())->toBeTrue();
            expect($validator->errors())->toHaveKey('name');
            expect($validator->errors())->toHaveKey('email');
        });
    });

    describe('validated()', function () {
        it('returns only validated fields', function () {
            $validator = Validator::make([
                'name' => 'John',
                'email' => 'john@example.com',
                'extra' => 'ignored',
            ], [
                'name' => 'required',
                'email' => 'required|email',
            ]);

            $validated = $validator->validated();

            expect($validated)->toHaveKey('name');
            expect($validated)->toHaveKey('email');
            expect($validated)->not->toHaveKey('extra');
        });
    });

    describe('validate() static method', function () {
        it('returns validated data on success', function () {
            $data = Validator::validate(['name' => 'John'], ['name' => 'required']);

            expect($data)->toBe(['name' => 'John']);
        });

        it('throws ValidationException on failure', function () {
            expect(fn() => Validator::validate([], ['name' => 'required']))->toThrow(ValidationException::class);
        });
    });

    describe('ValidationException', function () {
        it('contains errors', function () {
            try {
                Validator::validate([], ['name' => 'required', 'email' => 'required']);
            } catch (ValidationException $e) {
                expect($e->errors())->toHaveKey('name');
                expect($e->errors())->toHaveKey('email');
                expect($e->getFirstError())->toContain('required');
                expect($e->getMessages())->toHaveCount(2);
            }
        });
    });
});
