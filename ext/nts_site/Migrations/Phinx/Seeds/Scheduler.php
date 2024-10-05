<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;
use TYPO3\CMS\Core\Utility\CsvUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\CronCommand\CronCommand;
use TYPO3\CMS\Scheduler\Domain\Repository\SchedulerTaskRepository;
use TYPO3\CMS\Scheduler\Task\ExecuteSchedulableCommandTask;
use TYPO3\CMS\Scheduler\Task\RecyclerGarbageCollectionTask;
use TYPO3\CMS\Scheduler\Task\TableGarbageCollectionTask;

final class Scheduler extends AbstractSeed
{
    public function run(): void
    {
        $this->createDefaultTaskGroup();
        $this->createDefaultTasks();
    }

    private function createDefaultTaskGroup(): void
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

    private function createDefaultTasks(): void
    {
        if (!$this->hasTable('tx_scheduler_task_group') || !$this->hasTable('tx_scheduler_task')) {
            $this->output->writeln('<error>Table `tx_scheduler_task_group` and `tx_scheduler_task` not found</error>');
            return;
        }

        $schedulerTaskGroupTable = $this->table('tx_scheduler_task');
        $schedulerTaskGroupTable->truncate();

        $schedulerTaskRepository = GeneralUtility::makeInstance(SchedulerTaskRepository::class);

        // Clear old values in tables.
        $task = new TableGarbageCollectionTask();
        $task->setTaskGroup(1);
        $task->allTables = true;
        $task->numberOfDays = 30;
        $task->registerRecurringExecution(
            start: $this->getNextCronExecution('0 23 * * 5'),
            interval: 0,
            cron_cmd: '0 23 * * 5',
        );
        $schedulerTaskRepository->add($task);

        // Clear old files from recycler folders.
        $task = new RecyclerGarbageCollectionTask();
        $task->setTaskGroup(1);
        $task->numberOfDays = 30;
        $task->registerRecurringExecution(
            start: $this->getNextCronExecution('0 23 * * 5'),
            interval: 0,
            cron_cmd: '0 23 * * 5',
        );
        $schedulerTaskRepository->add($task);

        // Update reference index.
        $this->addCommandTask($schedulerTaskRepository, 'referenceindex:update', 1, '0 3 1 * *');

        // Cleanup commands.
        $this->addCommandTask($schedulerTaskRepository, 'cleanup:flexforms', 2, '0 5 1 * *');
        $this->addCommandTask($schedulerTaskRepository, 'cleanup:localprocessedfiles', 2, '0 5 1 * *');
        $this->addCommandTask($schedulerTaskRepository, 'cleanup:deletedrecords', 2, '0 5 1 * *');
        $this->addCommandTask($schedulerTaskRepository, 'cleanup:missingrelations', 2, '0 5 1 * *');
        $this->addCommandTask($schedulerTaskRepository, 'cleanup:orphanrecords', 2, '0 5 1 * *');
    }

    public function getNextCronExecution(string $cronCommand): int
    {
        $cronCmd = GeneralUtility::makeInstance(CronCommand::class, $cronCommand);
        $cronCmd->calculateNextValue();
        return $cronCmd->getTimestamp();
    }

    private function addCommandTask(SchedulerTaskRepository $schedulerTaskRepository, string $command, int $group, string $cron_cmd): void
    {
        $task = new ExecuteSchedulableCommandTask();
        $task->setTaskGroup($group);
        $task->setCommandIdentifier($command);
        $task->registerRecurringExecution(
            start: $this->getNextCronExecution($cron_cmd),
            interval: 0,
            cron_cmd: $cron_cmd,
        );
        $schedulerTaskRepository->add($task);
    }
}
