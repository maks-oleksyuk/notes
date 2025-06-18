<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\ColorScheme;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    #[\Override]
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    #[\Override]
    public function configureDashboard(): Dashboard
    {
        return parent::configureDashboard()
            ->setTitle('Symfony Notes')
            ->setDefaultColorScheme(ColorScheme::AUTO);
    }

    #[\Override]
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Users', 'fa fa-users', User::class);
    }
}
