<?php

class MultitaskQueuedTask extends AppModel {
	
	var $name = 'MultitaskQueuedTask';
	
	const STATUS_QUEUED = 0;
	const STATUS_COMPLETE = 1;
	const STATUS_INPROGRESS = 2;
	const STATUS_ERROR = -1;
	
	function getNextTask() {
		$task = $this->find('first', array('conditions' => array('status' => self::STATUS_QUEUED))); 
		if ($task) {
			return $task[$this->alias];
		}
		return $task;
	}
	
	function taskExecuting($id) {
		$this->updateAll(array('status' => self::STATUS_INPROGRESS), compact('id'));
	}
	
	function taskComplete($id) {
		$this->updateAll(array('status' => self::STATUS_COMPLETE), compact('id'));
	}
	
	function taskError($id) {
		$this->updateAll(array('status' => self::STATUS_ERROR), compact('id'));
	}
	
}

?>