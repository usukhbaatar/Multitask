<?php

class ThreadedTask extends Shell {
	
	var $data;
	var $pid;
	var $thread;
		
	function error($message, $code = null) {
		$this->thread->error($message, $code);
	}
	
	function out($data) {
		if (is_scalar($data)) {
			parent::out($data);
		} else {
			parent::out(print_r($data, true));
		}
	}
	
}

?>