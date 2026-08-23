<?php

use Illuminate\Support\Facades\Validator;

it('uses arabic as the application locale', function () {
    expect(app()->getLocale())->toBe('ar')
        ->and(config('app.fallback_locale'))->toBe('ar');
});

it('returns arabic messages for common validation rules', function () {
    $validator = Validator::make(
        [
            'email' => 'not-an-email',
            'phone' => '',
            'workplace' => '',
            'national_id' => '123',
        ],
        [
            'email' => ['required', 'email'],
            'phone' => ['required'],
            'workplace' => ['required'],
            'national_id' => ['digits:12'],
        ],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('email'))->toContain('بريداً إلكترونياً')
        ->and($validator->errors()->first('phone'))->toBe('حقل رقم الهاتف مطلوب.')
        ->and($validator->errors()->first('workplace'))->toBe('حقل مكان العمل مطلوب.')
        ->and($validator->errors()->first('national_id'))->toContain('12')
        ->and($validator->errors()->first('email'))->not->toContain('must be')
        ->and($validator->errors()->first('phone'))->not->toContain('required');
});

it('translates auth failures to arabic', function () {
    expect(__('auth.failed'))->toBe('بيانات الدخول غير صحيحة.')
        ->and(__('auth.throttle', ['seconds' => 60]))->toContain('ثانية');
});
