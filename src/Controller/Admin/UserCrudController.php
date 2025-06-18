<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\EasyAdmin\Field\LinkedTextField;
use App\Entity\User;
use App\Enum\UserRole;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

/**
 * @extends AbstractCrudController<User>
 */
final class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInPlural('Users')
            ->renderContentMaximized();
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();
        yield LinkedTextField::new('username')
            ->renderAsLinkToEntity();
        yield ChoiceField::new('roles')
            ->setChoices(array_column(UserRole::cases(), 'value', 'name'))
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setSortable(false);
    }
}
