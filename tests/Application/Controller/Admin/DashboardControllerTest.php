<?php

declare(strict_types=1);

namespace App\Tests\Application\Controller\Admin;

use App\Controller\Admin\DashboardController;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\ColorScheme;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MenuItemDto;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(DashboardController::class)]
final class DashboardControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private DashboardController $adminDashboardController;

    private User $adminUser;

    private User $simpleUser;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->adminDashboardController = self::getContainer()->get(DashboardController::class);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM App\Entity\User')->execute();

        $this->simpleUser = new User()->setUsername('simple_user')->setPassword('pass');
        $this->adminUser = new User()
            ->setUsername('admin_user')
            ->setPassword('pass')
            ->setRoles([UserRole::ADMIN->value]);

        $em->persist($this->simpleUser);
        $em->persist($this->adminUser);
        $em->flush();
    }

    public function testAdminRouteRequiresAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/admin');

        self::assertResponseRedirects('/login');
    }

    public function testDashboardIsForbiddenForRegularUser(): void
    {
        $this->client->loginUser($this->simpleUser);
        $this->client->request(Request::METHOD_GET, '/admin');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDashboardRendersForAdminUser(): void
    {
        $this->client->loginUser($this->adminUser);
        $this->client->request(Request::METHOD_GET, '/admin');

        self::assertResponseIsSuccessful();
        self::assertPageTitleSame('Symfony Notes | Dashboard');
    }

    public function testConfigureDashboardReturnsCorrectTitle(): void
    {
        $dashboard = $this->adminDashboardController
            ->configureDashboard()
            ->getAsDto();

        $this->assertSame('Symfony Notes', $dashboard->getTitle());
        $this->assertSame(ColorScheme::AUTO, $dashboard->getDefaultColorScheme());
    }

    public function testConfigureMenuItemsReturnsExpectedStructure(): void
    {
        $dashboard = $this->adminDashboardController;
        $menuItems = iterator_to_array($dashboard->configureMenuItems());

        $this->assertNotEmpty($menuItems);

        /** @var MenuItemDto $dto */
        $dto = $menuItems[0]->getAsDto();
        $this->assertSame(MenuItemDto::TYPE_DASHBOARD, $dto->getType());
        $this->assertSame('Dashboard', $dto->getLabel());
        $this->assertSame('fa fa-home', $dto->getIcon());

        $dto = $menuItems[1]->getAsDto();
        $this->assertSame(MenuItemDto::TYPE_CONTROLLER, $dto->getType());
        $this->assertSame('Users', $dto->getLabel());
        $this->assertSame('fa fa-users', $dto->getIcon());
    }
}
