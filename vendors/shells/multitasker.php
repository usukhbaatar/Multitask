<?php

App::import('Vendor', 'PHP_Fork', array('plugin' => 'multitask', 'file' => 'php_fork'.DS.'Fork.php'));

class MultitaskerShell extends Shell {
	
	var $threads = array();
	var $maxThreads = 5;
	var $TaskModel = null;
	
	function main() {
		
		$taskModel = Configure::read('plugins.multitask.taskModel');
		if (empty($taskModel)) {
			$taskModel = 'Multitask.MultitaskQueuedTask';
		}
		$this->TaskModel = ClassRegistry::init($taskModel);
		
		$maxThreads = Configure::read('plugins.multitask.maxThreads');
		if (is_int($maxThreads)) {
			$this->maxThreads = $maxThreads;
		}
		
		// if we are on linux then enter multithread mode
		// otherwise we just do a single threaded loop
		
		if (DS == '/') {
			$this->loop();
		} else {
			$this->singleThreadLoop();
		}
		
	}
	
	function &getNextIdleThread() {
		for ($i=0;$i<$this->maxThreads;$i++) {
			$thread = &$this->threads[$i];
			if (empty($thread)) {
				// start a new thread
				$thread = new PseudoThread('Multitasker'.$i);
				$thread->Dispatch = &$this->Dispatch;
				$thread->start();
				$thread->setVariable('pid', $thread->getPid());
				$thread->setVariable('status', PseudoThread::IDLE);
				$this->threads[$i] = &$thread;
				
				// establish a new connection for all data sources
				// for some reason whenever we fork and create a new connection it kills this one
				foreach (ConnectionManager::sourceList() as $sourceName) {
					ConnectionManager::getDataSource($sourceName)->connect();
				}
			
				return $thread;
			} elseif ($thread->getVariable('status') === PseudoThread::IDLE) {
				return $thread;
			}
		}
		$return = false;
		return $return;
	}
		
	function loop() {
		
		while (true) {
			
			$this->cleanupCompletedThreads();
			$this->cleanupDeadThreads();
			
			$thread = &$this->getNextIdleThread();
			
			if (!$thread) {
				// sleep for 0.2 seconds until we have more threads (hopefully)
				usleep(200000);
				continue;
			}
			
			while (!($task = $this->TaskModel->getNextTask())) {
				// have a sleep for a second until we get more tasks (hopefully)
				sleep(1);
				$this->cleanupCompletedThreads();
			}
			
			$this->TaskModel->taskExecuting($task['id']);
			$this->setupThread($thread, $task);
			
		}
		
	}
	
	function singleThreadLoop() {
		
		$thread = new DummyThread();
		
		while (true) {
			
			while (!($task = $this->TaskModel->getNextTask())) {
				// have a sleep for a second until we get more tasks (hopefully)
				sleep(1);
			}
			
			$id = $task['id'];
			$taskName = $task['task'];
			$methodName = $task['method'];
			$data = $task['data'];
			
			$thread->setVariable('taskId', $id);
			$thread->setVariable('task', $taskName);
			$thread->setVariable('method', $methodName);
			$thread->setVariable('data', $data);
			$thread->setVariable('status', PseudoThread::BUSY);
			
			$this->TaskModel->taskExecuting($id);
			
			if (!$this->loadTask($taskName)) {
				continue;
			}
			
			$taskClass = &$this->{$taskName};
			
			if (empty($methodName)) {
				$methodName = 'execute';
			}
			
			$taskClass->thread = &$thread;
			$taskClass->pid = 1; // just a single thread, so we just give a fake process id
			$taskClass->data = &$data;
			
			$taskClass->{$methodName}();
			
			if ($thread->getVariable('status') === PseudoThread::ERROR) {
				$this->TaskModel->taskError($id);
			} else {
				$this->TaskModel->taskComplete($id);
			}
			
		}

	}
	
	function cleanupDeadThreads() {
		$cmd = 'ps axwww | grep cake.php | awk \'{print $1}\'';
		exec($cmd, $output);
		foreach ($this->threads as $i => $thread) {
			if (array_search($thread->getPid(), $output) === false) {
				$thread->stop();
				unset($this->threads[$i]);
			}
		}
	}
	
	function cleanupCompletedThreads() {
		foreach ($this->threads as $thread) {
			switch ($thread->getVariable('status')) {
				case PseudoThread::SUCCESS:
					$this->TaskModel->taskComplete($thread->getVariable('taskId'));
					$this->cleanupThread($thread);
					break;
				case PseudoThread::ERROR: 
					$this->TaskModel->taskError($thread->getVariable('taskId'));
					$this->cleanupThread($thread);
					break;
			}
		}
	}
	
	function setupThread(&$thread, &$task) {
		$thread->setVariable('taskId', $task['id']);
		$thread->setVariable('task', $task['task']);
		$thread->setVariable('method', $task['method']);
		$thread->setVariable('data', $task['data']);
		$thread->setVariable('status', PseudoThread::READY);
	}
	
	function cleanupThread(&$thread) {
		$thread->setVariable('taskId', null);
		$thread->setVariable('task', null);
		$thread->setVariable('method', null);
		$thread->setVariable('data', null);
		$thread->setVariable('status', PseudoThread::IDLE);
	}
	
	function _stop() {
		foreach ($this->threads as $thread) {
			$thread->stop();
		}
		parent::_stop();
	}
	
	
	function loadTask($taskName) {
		
		if (!empty($this->{$taskName})) {
			return true;
		}
		
		$task = Inflector::underscore($taskName);
		$taskClass = Inflector::camelize($taskName . 'Task');

		if (!class_exists($taskClass)) {
			foreach ($this->Dispatch->shellPaths as $path) {
				$taskPath = $path . 'tasks' . DS . $task.'.php';
				if (file_exists($taskPath)) {
					require_once $taskPath;
					break;
				}
			}
		}
		
		if (ClassRegistry::isKeySet($taskClass)) {
			$this->taskNames[] = $taskName;
			if (!PHP5) {
				$this->{$taskName} =& ClassRegistry::getObject($taskClass);
			} else {
				$this->{$taskName} = ClassRegistry::getObject($taskClass);
			}
		} else {
			$this->taskNames[] = $taskName;
			if (!PHP5) {
				$this->{$taskName} =& new $taskClass($this->Dispatch);
			} else {
				$this->{$taskName} = new $taskClass($this->Dispatch);
			}
		}
		
		if (!isset($this->{$taskName})) {
			return false;
		}
		
		// we should really only initialize these if they subclass Shell, but whatever!
		
		$this->{$taskName}->initialize();
		$this->{$taskName}->loadTasks();
		
		foreach ($this->{$taskName}->taskNames as $subTask) {
			$this->{$taskName}->{$subTask}->initialize();
			$this->{$taskName}->{$subTask}->loadTasks();
		}
									
		return true;
		
	}
	
}

class DummyThread {
	
	const IDLE = 0;
	const READY = 1;
	const BUSY = 2;
	const SUCCESS = 3;
	const ERROR = 4;
	
	var $variables = array();
	
	function setVariable($name, $value) {
		$this->variables[$name] = $value;
	}
	
	function getVariable($name) {
		if (array_key_exists($name, $this->variables)) {
			return $this->variables[$name];
		} else {
			return null;
		}
	}
	
	function error($message, $code = null) {
		$this->setVariable('status', self::ERROR);
	}
	
}

class PseudoThread extends PHP_Fork {
	
	const IDLE = 0;
	const READY = 1;
	const BUSY = 2;
	const SUCCESS = 3;
	const ERROR = 4;
	
	var $Dispatch;
	var $taskId;
	
	function loadTask($taskName) {
		
		if (!empty($this->{$taskName})) {
			return true;
		}
		
		$task = Inflector::underscore($taskName);
		$taskClass = Inflector::camelize($taskName . 'Task');

		if (!class_exists($taskClass)) {
			foreach ($this->Dispatch->shellPaths as $path) {
				$taskPath = $path . 'tasks' . DS . $task.'.php';
				if (file_exists($taskPath)) {
					require_once $taskPath;
					break;
				}
			}
		}
		
		if (ClassRegistry::isKeySet($taskClass)) {
			$this->taskNames[] = $taskName;
			if (!PHP5) {
				$this->{$taskName} =& ClassRegistry::getObject($taskClass);
			} else {
				$this->{$taskName} = ClassRegistry::getObject($taskClass);
			}
		} else {
			$this->taskNames[] = $taskName;
			if (!PHP5) {
				$this->{$taskName} =& new $taskClass($this->Dispatch);
			} else {
				$this->{$taskName} = new $taskClass($this->Dispatch);
			}
		}
		
		if (!isset($this->{$taskName})) {
			return false;
		}
		
		// we should really only initialize these if they subclass Shell, but whatever!
		
		$this->{$taskName}->initialize();
		$this->{$taskName}->loadTasks();
		
		foreach ($this->{$taskName}->taskNames as $subTask) {
			$this->{$taskName}->{$subTask}->initialize();
			$this->{$taskName}->{$subTask}->loadTasks();
		}
									
		return true;
		
	}
	
	
	function run() {
		
		// establish a new connection for all data sources
		foreach (ConnectionManager::sourceList() as $sourceName) {
			ConnectionManager::getDataSource($sourceName)->connect();
		}
		
		while (true) {
			if ($this->getVariable('status') === self::READY) {
				
				$taskName = $this->getVariable('task');
				$methodName = $this->getVariable('method');
				
				if (empty($methodName)) {
					$methodName = 'execute';
				}
				
				// try and load the task
				if (!$this->loadTask($taskName)) {
					$this->setVariable('status', self::ERROR);
					continue;
				}
				
				$this->setVariable('status', self::BUSY);
				$task = &$this->{$taskName};
				
				$task->thread = &$this;
				$task->pid = $this->getVariable('pid');
				$task->data = &$this->getVariable('data');
				
				// execute the task here				
				$task->{$methodName}();
				
				// if status hasn't changed, then we mark it as a success!
				if ($this->getVariable('status') === self::BUSY) {
					$this->setVariable('status', self::SUCCESS);
				}
				
			} else {
				// sleep for 10000 nanoseconds while we wait for more intructions
				usleep(10000);
			}
		}
	}
	
	function error($message, $code = null) {
		$this->setVariable('status', self::ERROR);
	}
	
	function stop() {
		$this->setVariable('status', self::IDLE);
		parent::stop();
	}
	
}

?>