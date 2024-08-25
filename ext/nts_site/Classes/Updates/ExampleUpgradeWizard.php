<?php

declare(strict_types=1);

namespace Typo3Notes\NtsSite\Updates;

use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

#[UpgradeWizard('nts_site_exampleUpgradeWizard')]
final class ExampleUpgradeWizard implements UpgradeWizardInterface
{
    /**
     * {@inheritdoc}
     */
    public function getTitle(): string
    {
        return 'Title of this updater';
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return 'Description of this updater';
    }

    /**
     * {@inheritdoc}
     */
    public function executeUpdate(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function updateNecessary(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrerequisites(): array
    {
        return [];
    }
}
