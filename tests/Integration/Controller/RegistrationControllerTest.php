<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Controller\RegistrationController;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(RegistrationController::class)]
final class RegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $container = self::getContainer();
        $this->userRepository = $container->get(UserRepository::class);

        $em = $container->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    public function testSuccessfulRegistration(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/register');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Register')->form([
            'registration_form[username]' => 'john_doe',
            'registration_form[password][first]' => 'Strong123!',
            'registration_form[password][second]' => 'Strong123!',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();

        $user = $this->userRepository->findOneByUsername('john_doe');
        $this->assertInstanceOf(User::class, $user);
        $this->assertNotSame('Strong123!', $user->getPassword());
        $this->assertIsString($user->getPassword());
        $this->assertStringStartsWith('$', $user->getPassword());
    }

    public function testFailedRegistrationDueToInvalidPassword(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/register');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Register')->form([
            'registration_form[username]' => 'jane_doe',
            'registration_form[password][first]' => 'short1!',
            'registration_form[password][second]' => 'short1!',
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertNotInstanceOf(User::class, $this->userRepository->findOneByUsername('jane_doe'));
    }
}
