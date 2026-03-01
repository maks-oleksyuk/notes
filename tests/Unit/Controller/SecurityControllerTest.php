<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\SecurityController;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[CoversMethod(SecurityController::class, 'logout')]
final class SecurityControllerTest extends TestCase
{
    public function testLogoutMethodThrowsLogicException(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('This method can be blank - it will be intercepted by the logout key on your firewall.');

        $authenticationUtilsMock = $this->createStub(AuthenticationUtils::class);
        $controller = new SecurityController($authenticationUtilsMock);
        $controller->logout();
    }
}
