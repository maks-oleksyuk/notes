<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\EasyAdmin\Field\LinkedTextField;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

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
        return [
            IdField::new('id')
                ->hideOnForm(),
            LinkedTextField::new('username')
                ->renderAsLinkToEntity(),
            // todo: Add enum support.
            ArrayField::new('roles')
                ->setSortable(false),
        ];
    }
}
