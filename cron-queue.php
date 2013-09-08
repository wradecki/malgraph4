<?php
require_once 'src/core.php';
CronRunner::run(__FILE__, function($logger)
{
	$userProcessor = new UserProcessor();
	$queue = new Queue(Config::$userQueuePath);
	$cache = new Cache();

	$processed = 0;
	while ($processed < Config::$usersPerCronRun)
	{
		$userName = $queue->dequeue();
		if ($userName === null)
		{
			break;
		}

		try
		{
			$logger->log('Processing user %s', $userName);

			$query = 'SELECT 0 FROM user WHERE LOWER(name) = LOWER(?)' .
				' AND processed >= DATETIME("now", "-1 days")';
			if (R::getAll($query, [$userName]))
			{
				$logger->log('Too soon');
				continue;
			}
			++ $processed;
			$userProcessor->process($userName);

			$cache->setPrefix($userName);
			foreach ($cache->getAllFiles() as $path)
			{
				unlink($path);
			}
		}
		catch (BadProcessorKeyException $e)
		{
			$logger->log($e->getMessage());
		}
		catch (DownloadFailureException $e)
		{
			$logger->log($e->getMessage());
			$queue->enqueue($userName);
		}
		catch (Exception $e)
		{
			$logger->log($e);
			$queue->enqueue($userName);
		}
	}
});
