<?php

class MultitaskQueuedTask extends AppModel {
	
	var $name = 'MultitaskQueuedTask';
	
	const STATUS_QUEUED = 0;
	const STATUS_COMPLETE = 1;
	const STATUS_INPROGRESS = 2;
	const STATUS_ERROR = -1;
	
	function beforeSave() {
		$data =& $this->data[$this->alias];
		if (empty($this->id) && array_key_exists($data['data'])) {
			if (!$this->is_serialized($data['data'])) {
				$data['data'] = serialize($data['data']);
			}
		}
		return parent::beforeSave();
	}
	
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
	
	function is_serialized($data) {
		// if it isn't a string, it isn't serialized
		if ( !is_string($data) )
			return false;
		$data = trim($data);
		if ( 'N;' == $data )
			return true;
		if ( !preg_match('/^([adObis]):/', $data, $badions) )
			return false;
		switch ( $badions[1] ) :
		case 'a' :
		case 'O' :
		case 's' :
			if ( preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data) )
				return true;
			break;
		case 'b' :
		case 'i' :
		case 'd' :
			if ( preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data) )
				return true;
			break;
		endswitch;
		return false;
	}
	
}

?>