<?php

class MultitaskQueuedTasksController extends AppController {
	
	var $name = 'MultitaskQueuedTasks';
	
	function populate($num = 1) {
		set_time_limit(3600);
		$num = intval($num);
		$db =& ConnectionManager::getDataSource($this->MultitaskQueuedTask->useDbConfig);
		while ($num-- > 0) {
			/*$sql = "INSERT INTO multitask_queued_tasks VALUES ('', 'Sample', null, '".serialize(array('rand' => mt_rand(0, 100)))."', 0, NOW(), NOW())";
			$db->execute($sql);
			usleep(2000);*/
			$data = array(
				'task' => 'Sample',
				'data' => serialize(array('rand' => mt_rand(0, 100)))
			);
			$this->MultitaskQueuedTask->create($data, true);
			$this->MultitaskQueuedTask->save();
		}
		exit;
	}
	
}

?>