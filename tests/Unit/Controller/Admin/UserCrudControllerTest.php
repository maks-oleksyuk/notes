<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\UserCrudController;
use App\EasyAdmin\Field\LinkedTextField;
use App\Entity\User;
use App\Enum\UserRole;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
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
        $this->assertSame(User::class, UserCrudController::getEntityFqcn());
    }

    public function testConfigureCrudReturnsCrudInstance(): void
    {
        $result = $this->controller->configureCrud(Crud::new())->getAsDto();

        $this->assertSame('Users', $result->getEntityLabelInPlural());
        $this->assertSame(Crud::LAYOUT_CONTENT_FULL, $result->getContentWidth());
    }

    #[DataProvider('pageNameProvider')]
    public function testConfigureFieldsReturnsFieldsForPage(string $pageName): void
    {
        $fieldsIterable = $this->controller->configureFields($pageName);
        $fields = \is_array($fieldsIterable) ? $fieldsIterable : iterator_to_array($fieldsIterable);

        $this->assertCount(3, $fields);

        $this->assertInstanceOf(IdField::class, $fields[0]);
        $this->assertInstanceOf(LinkedTextField::class, $fields[1]);
        $this->assertInstanceOf(ChoiceField::class, $fields[2]);

        $idFieldDto = $fields[0]->getAsDto();
        $this->assertSame('id', $idFieldDto->getProperty());
        $this->assertTrue($idFieldDto->isDisplayedOn(Crud::PAGE_INDEX));
        $this->assertTrue($idFieldDto->isDisplayedOn(Crud::PAGE_DETAIL));
        $this->assertFalse($idFieldDto->isDisplayedOn(Crud::PAGE_NEW));
        $this->assertFalse($idFieldDto->isDisplayedOn(Crud::PAGE_EDIT));

        $usernameFieldDto = $fields[1]->getAsDto();
        $this->assertSame('username', $usernameFieldDto->getProperty());
        $this->assertTrue($usernameFieldDto->getCustomOption(LinkedTextField::OPTION_RENDER_AS_LINK_TO_ENTITY));

        $rolesFieldDto = $fields[2]->getAsDto();
        $this->assertSame('roles', $rolesFieldDto->getProperty());
        $this->assertSame(array_column(UserRole::cases(), 'value', 'name'), $rolesFieldDto->getCustomOption(ChoiceField::OPTION_CHOICES));
        $this->assertTrue($rolesFieldDto->getCustomOption(ChoiceField::OPTION_ALLOW_MULTIPLE_CHOICES));
        $this->assertTrue($rolesFieldDto->getCustomOption(ChoiceField::OPTION_RENDER_EXPANDED));
        $this->assertFalse($rolesFieldDto->isSortable());
    }

    /**
     * @return \Iterator<(int | string), array{string}>
     */
    public static function pageNameProvider(): \Iterator
    {
        yield ['index'];
        yield ['detail'];
        yield ['edit'];
        yield ['new'];
    }
}
