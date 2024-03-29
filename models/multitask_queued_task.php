<?php

class MultitaskQueuedTask extends AppModel {
	
	var $name = 'MultitaskQueuedTask';
	
	const STATUS_QUEUED = 0;
	const STATUS_COMPLETE = 1;
	const STATUS_INPROGRESS = 2;
	const STATUS_ERROR = -1;
	
	var $validate = array(
		'task' => array(
			'rule' => 'notEmpty'
		)
	);
		
	function beforeSave() {
		$data =& $this->data[$this->alias];
		if (empty($this->id) && array_key_exists('data', $data)) {
			if (!$this->is_serialized($data['data'])) {
				$data['data'] = serialize($data['data']);
			}
		}
		return parent::beforeSave();
	}
	
	function getNextTask() {
		$task = $this->find('first', array('conditions' => array('status' => self::STATUS_QUEUED))); 
		if ($task) {
			$task = $task[$this->alias];
			if ($this->is_serialized($task['data'])) {
				$task['data'] = unserialize($task['data']);
			}
		}
		return $task;
	}
	
	function taskExecuting($id) {
		$this->id = $id;
		$this->saveField('status', self::STATUS_INPROGRESS);
	}
	
	function taskComplete($id) {
		$this->id = $id;
		$this->saveField('status', self::STATUS_COMPLETE);
	}
	
	function taskError($id) {
		$this->id = $id;
		$this->saveField('status', self::STATUS_ERROR);
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