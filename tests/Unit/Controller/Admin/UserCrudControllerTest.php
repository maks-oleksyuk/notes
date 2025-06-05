<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\UserCrudController;
use App\EasyAdmin\Field\LinkedTextField;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserCrudController::class)]
final class UserCrudControllerTest extends TestCase
{
    private UserCrudController $controller;

    protected function setUp(): void
    {
        $this->controller = new UserCrudController();
    }

    public function testGetEntityFqcnReturnsUserClass(): void
    {
        self::assertSame(User::class, UserCrudController::getEntityFqcn());
    }

    public function testConfigureCrudReturnsCrudInstance(): void
    {
        $result = $this->controller->configureCrud(Crud::new())->getAsDto();

        self::assertSame('Users', $result->getEntityLabelInPlural());
        self::assertSame($result->getContentWidth(), Crud::LAYOUT_CONTENT_FULL);
    }

    #[DataProvider('pageNameProvider')]
    public function testConfigureFieldsReturnsFieldsForPage(string $pageName): void
    {
        $fieldsIterable = $this->controller->configureFields($pageName);
        $fields = \is_array($fieldsIterable) ? $fieldsIterable : iterator_to_array($fieldsIterable);

        self::assertCount(3, $fields);

        self::assertInstanceOf(IdField::class, $fields[0]);
        self::assertInstanceOf(LinkedTextField::class, $fields[1]);
        self::assertInstanceOf(ArrayField::class, $fields[2]);

        $idFieldDto = $fields[0]->getAsDto();
        self::assertSame('id', $idFieldDto->getProperty());
        self::assertTrue($idFieldDto->isDisplayedOn(Crud::PAGE_INDEX));
        self::assertTrue($idFieldDto->isDisplayedOn(Crud::PAGE_DETAIL));
        self::assertFalse($idFieldDto->isDisplayedOn(Crud::PAGE_NEW));
        self::assertFalse($idFieldDto->isDisplayedOn(Crud::PAGE_EDIT));

        $usernameFieldDto = $fields[1]->getAsDto();
        self::assertSame('username', $usernameFieldDto->getProperty());
        self::assertTrue($usernameFieldDto->getCustomOption(LinkedTextField::OPTION_RENDER_AS_LINK_TO_ENTITY));

        $rolesFieldDto = $fields[2]->getAsDto();
        self::assertSame('roles', $rolesFieldDto->getProperty());
        self::assertFalse($rolesFieldDto->isSortable());
    }

    /**
     * @return array<array{string}>
     */
    public static function pageNameProvider(): array
    {
        return [
            ['index'],
            ['detail'],
            ['edit'],
            ['new'],
        ];
    }
}
