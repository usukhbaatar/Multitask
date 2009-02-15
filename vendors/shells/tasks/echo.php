<?php

App::import('Vendor', 'ThreadedTask', array('plugin' => 'multitask', 'file' => 'shells'.DS.'tasks'.DS.'threaded_task.php'));

class EchoTask extends ThreadedTask {
	
	function execute() {
		$this->out($this->data);
	}
	
	function delayed() {
		$this->out('Sleeping for '.$this->data['duration'].' seconds ['.$this->pid.']');
		sleep($this->data['duration']);
		$this->out($this->data['message']);
	}
	
}


/**

Example usage:

	$this->MultitaskQueuedTask = ClassRegistry::init('Multitask.MultitaskQueuedTask');
	
	$task = array(
		'task' => 'echo',
		'data' => 'Hello, World!',
	);
	
	$this->MultitaskQueuedTask->create($task, true);
	$this->MultitaskQueuedTask->save();
	
	
	$task = array(
		'task' => 'echo',
		'method' => 'delayed',
		'data' => array('duration' => 3, 'message' => 'That was a nice sleep!'),
	);
	
	$this->MultitaskQueuedTask->create($task, true);
	$this->MultitaskQueuedTask->save();

*/

?>