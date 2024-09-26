<?php

namespace SilverStripe\StaticPublishQueue\Task;

use DateTime;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\StaticPublishQueue\Job\StaticCacheFullBuildJob;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class StaticCacheFullBuildTask extends BuildTask
{
    protected static string $commandName = 'static-cache-full-build';

    protected string $title = 'Static Cache Full Build';

    protected static string $description = 'Fully build the static cache for the whole site';

    /**
     * Queue up a StaticCacheFullBuildJob
     * Check for startAfter param and do some sanity checking
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $job = Injector::inst()->create(StaticCacheFullBuildJob::class);
        $signature = $job->getSignature();

        // see if we already have this job in a queue
        $filter = [
            'Signature' => $signature,
            'JobStatus' => [
                QueuedJob::STATUS_NEW,
                QueuedJob::STATUS_INIT,
            ],
        ];

        $existing = DataList::create(QueuedJobDescriptor::class)->filter($filter)->first();

        if ($existing && $existing->exists()) {
            $output->writeln(sprintf(
                'There is already a %s in the queue, added %s %s',
                StaticCacheFullBuildJob::class,
                $existing->Created,
                $existing->StartAfter ? 'and set to start after ' . $existing->StartAfter : ''
            ));

            return Command::FAILURE;
        }

        if ($input->getOption('startAfter')) {
            $now = DBDatetime::now();
            $today = $now->Date();
            $startTime = $input->getOption('startAfter');

            // move to tomorrow if the starttime has passed today
            if ($now->Time24() > $startTime) {
                $timestamp = strtotime($today . ' ' . $startTime . ' +1 day');
                $dayWord = 'tomorrow';
            } else {
                $timestamp = strtotime($today . ' ' . $startTime);
                $dayWord = 'today';
            }

            $startAfter = (new DateTime())->setTimestamp($timestamp);
            $thisTimeTomorrow = (new DateTime())->setTimestamp(strtotime($now . ' +1 day'))->getTimestamp();

            // sanity check that we are in the next 24 hours - prevents some weird stuff sneaking through
            if ($startAfter->getTimestamp() > $thisTimeTomorrow || $startAfter->getTimestamp() < $now->getTimestamp()) {
                $output->writeln('Invalid startAfter parameter passed. Please ensure the time format is HHmm e.g. 1300');

                return Command::INVALID;
            }

            $output->writeln(sprintf(
                '%s queued for %s %s.',
                StaticCacheFullBuildJob::class,
                $startAfter->format('H:m'),
                $dayWord
            ));
        } else {
            $startAfter = null;
            $output->writeln(StaticCacheFullBuildJob::class . ' added to the queue for immediate processing');
        }

        $job->setJobData(0, 0, false, new \stdClass(), [
            'Building static cache for full site',
        ]);
        QueuedJobService::singleton()->queueJob($job, $startAfter ? $startAfter->format('Y-m-d H:i:s') : null);

        return Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption('startAfter', null, InputOption::VALUE_REQUIRED, 'Delay execution until this time. Must be in 24hr format e.g. <comment>1300</comment>'),
        ];
    }
}
