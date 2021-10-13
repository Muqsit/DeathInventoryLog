<?php

declare(strict_types=1);

namespace muqsit\deathinventorylog\purger;

use InvalidArgumentException;
use muqsit\deathinventorylog\Loader;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;

final class PeriodicLogPurger implements LogPurger{

	public static function fromConfiguration(Loader $loader, array $data) : self{
		$time_period = strtotime("+" . $data["period"], 0);
		if($time_period === false || $time_period === 0){
			throw new InvalidArgumentException("Invalid time period specified: {$data["period"]}");
		}

		return new self($loader, $time_period);
	}

	private TaskHandler $task_handler;

	private function __construct(Loader $loader, int $time_period){
		$this->task_handler = $loader->getScheduler()->scheduleRepeatingTask(new ClosureTask(static function() use($loader, $time_period) : void{
			$loader->getDatabase()->purge(time() - $time_period, static function(int $deleted) use($loader) : void{
				if($deleted > 0){
					$loader->getLogger()->debug("Purged {$deleted} log" . ($deleted === 1 ? "" : "s"));
				}
			});
		}), 20 * 60);
	}

	public function close() : void{
		$this->task_handler->cancel();
	}
}