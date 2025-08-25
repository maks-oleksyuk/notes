<?php
declare(strict_types=1);

/*
Note: Detected testing framework: Pest (with PHPUnit expectations).
These tests target the diff-added page class: App\Filament\Resources\User\Pages\ViewUser.
They validate structure, inheritance, and resource binding without requiring HTTP or DB.
*/

use App\Filament\Resources\User\Pages\ViewUser;
use App\Filament\Resources\User\UserResource;
use Filament\Resources\Pages\ViewRecord;

use ReflectionClass;
use ReflectionMethod;

it('is a final class', function () {
    $ref = new ReflectionClass(ViewUser::class);
    expect($ref->isFinal())->toBeTrue();
});

it('extends Filament ViewRecord', function () {
    expect(is_subclass_of(ViewUser::class, ViewRecord::class))->toBeTrue();
});

it('declares a protected static string $resource property referencing the UserResource class', function () {
    $ref = new ReflectionClass(ViewUser::class);
    expect($ref->hasProperty('resource'))->toBeTrue();

    $prop = $ref->getProperty('resource');
    expect($prop->isStatic())->toBeTrue();
    expect($prop->isProtected())->toBeTrue();
    expect($prop->hasType())->toBeTrue();
    expect($prop->getType()->getName())->toBe('string');

    $defaults = $ref->getDefaultProperties();
    expect($defaults)->toHaveKey('resource');
    expect($defaults['resource'])->toBe(UserResource::class);
    expect(class_exists($defaults['resource']))->toBeTrue();
});

it('does not declare any custom methods (relies on base ViewRecord behavior)', function () {
    $ref = new ReflectionClass(ViewUser::class);
    $declaredHere = array_filter(
        $ref->getMethods(),
        fn (ReflectionMethod $m) => $m->getDeclaringClass()->getName() === ViewUser::class
    );
    expect($declaredHere)->toBeArray()->toHaveCount(0);
});

it('exposes the resource via getResource() when available', function () {
    if (method_exists(ViewUser::class, 'getResource')) {
        expect(ViewUser::getResource())->toBe(UserResource::class);
    } else {
        test()->markTestSkipped('ViewUser::getResource() not available in this Filament version.');
    }
});