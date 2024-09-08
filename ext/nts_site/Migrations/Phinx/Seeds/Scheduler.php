<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;
use TYPO3\CMS\Core\Utility\CsvUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class Scheduler extends AbstractSeed
{
    public function run(): void
    {
        if (!$this->hasTable('tx_scheduler_task_group')) {
            $this->output->writeln('<error>Table `tx_scheduler_task_group` not found</error>');
            return;
        }

        $csvFilePath = GeneralUtility::getFileAbsFileName('EXT:nts_site/Resources/Private/SeederData/tx_scheduler_task_group.csv');
        $csvArray = CsvUtility::csvToArray((string)file_get_contents($csvFilePath));

        $headers = array_shift($csvArray);
        $formattedArray = array_map(fn($row) => array_combine($headers, $row), $csvArray);

        $schedulerTaskGroupTable = $this->table('tx_scheduler_task_group');
        $schedulerTaskGroupTable->truncate();
        $schedulerTaskGroupTable->insert($formattedArray)->saveData();
    }
}
