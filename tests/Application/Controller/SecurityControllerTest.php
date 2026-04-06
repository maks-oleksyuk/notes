<?php

declare(strict_types=1);

namespace App\Tests\Application\Controller;

use App\Controller\SecurityController;
use App\Tests\Helper\UserCreatorTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[CoversClass(SecurityController::class)]
final class SecurityControllerTest extends WebTestCase
{
    use UserCreatorTrait;

    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->hasher = $container->get(UserPasswordHasherInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    public function testLoginPageLoadsSuccessfully(): void
    {
        $this->client->request(Request::METHOD_GET, '/login');

        self::assertResponseIsSuccessful();
        self::assertPageTitleSame('Sign in');
        self::assertSelectorExists('.content>form');
        self::assertInputValueSame('_csrf_token', 'csrf-token');
        self::assertInputValueSame('_target_path', '/admin');
        self::assertSelectorTextSame('.content>form label[for="username"]', 'Username');
        self::assertSelectorTextSame('.content>form label[for="password"]', 'Password');
        self::assertSelectorTextSame('.content>form>button[type="submit"]', 'Sign in');
        self::assertSelectorExists('.content>form #remember_me');
        self::assertCheckboxChecked('_remember_me');
        self::assertSelectorTextSame('.content>form label[for="remember_me"]', 'Remember me');
    }

    public function testLoginWithValidCredentialsRedirectsToAdmin(): void
    {
        self::createAdminUser($this->em, $this->hasher);

        $crawler = $this->client->request(Request::METHOD_GET, '/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'admin',
            '_password' => 'admin',
        ]);

        $this->client->submit($form);
        self::assertResponseRedirects('/admin');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testLoggedInUserIsRedirectedFromLoginToAdmin(): void
    {
        $adminUser = $this->createAdminUser($this->em, $this->hasher);

        $this->client->loginUser($adminUser);
        $this->client->request(Request::METHOD_GET, '/login');

        self::assertResponseRedirects('/admin');
    }

    public function testLoginWithInvalidCredentialsShowsError(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/login');
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'test',
            '_password' => 'wrong_password',
        ]);

        $this->client->submit($form);
        self::assertResponseRedirects('/login');

        $this->client->followRedirect();
        self::assertSelectorExists('.content>form');
        self::assertSelectorExists('.content>.alert-danger');
    }

    public function testLogoutRouteLogsOutUserAndRedirects(): void
    {
        $adminUser = $this->createAdminUser($this->em, $this->hasher);

        $this->client->loginUser($adminUser);
        $this->client->request(Request::METHOD_GET, '/logout');
        self::assertResponseRedirects('/');
    }
}
