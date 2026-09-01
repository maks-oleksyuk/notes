<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\UserPolicy;

covers(UserPolicy::class);

beforeEach(function (): void {
    $this->policy = new UserPolicy;
    $this->admin = User::factory()->superAdmin()->create();
    $this->member = User::factory()->create();
    $this->other = User::factory()->create();
});

describe('Policy | User', function (): void {
    it('allows only an admin to list users', function (): void {
        expect($this->policy->viewAny($this->admin))->toBeTrue()
            ->and($this->policy->viewAny($this->member))->toBeFalse();
    });

    it('allows an admin to view anyone and a member only itself', function (): void {
        expect($this->policy->view($this->admin, $this->other))->toBeTrue()
            ->and($this->policy->view($this->member, $this->member))->toBeTrue()
            ->and($this->policy->view($this->member, $this->other))->toBeFalse();
    });

    it('allows only an admin to create users', function (): void {
        expect($this->policy->create($this->admin))->toBeTrue()
            ->and($this->policy->create($this->member))->toBeFalse();
    });

    it('allows an admin to update anyone and a member only itself', function (): void {
        expect($this->policy->update($this->admin, $this->other))->toBeTrue()
            ->and($this->policy->update($this->member, $this->member))->toBeTrue()
            ->and($this->policy->update($this->member, $this->other))->toBeFalse();
    });

    it('allows an admin to delete others but never itself, and forbids members', function (): void {
        expect($this->policy->delete($this->admin, $this->other))->toBeTrue()
            ->and($this->policy->delete($this->admin, $this->admin))->toBeFalse()
            ->and($this->policy->delete($this->member, $this->other))->toBeFalse();
    });
});
