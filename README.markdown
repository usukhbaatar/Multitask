Multitask Plugin
================

Version 0.0.1

by Adam Royle <adam@sleekgeek.com.au>

It uses PHP_Fork written by Luca Mariano <luca.mariano@email.it>


What is multitask?
------------------

Multitask is a CakePHP plugin designed as a proof of concept task manager for non-interactive tasks.

It consists of:

1. MultitaskerShell - a daemon that manages the tasks/threads
2. MultitaskQueuedTask - a model for adding queued tasks
3. ThreadedTask - a base class for your tasks


What does it do?
----------------

The multitasker shell acts as a daemon, getting a task from a model, executing it, and updating its status when done. 

It could be used for executing long-running tasks in the background, such as tasks involving significant network activity, encoding video, etc.


Installation & Usage
--------------------

1. Copy the "multitask" folder into the plugins folder of your CakePHP app.
2. Import the database schema from config/multitask.sql
3. Run "multitasker" shell from the command line.

	cd cake/console/
	./cake multitasker -app ../../app

Then add some tasks into your database and it will execute them. :)

Here is some code that will add a task into the queue. Look in vendors/shells/tasks/echo.php for the task being executed.

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


Known Issues
------------

I've not used this in production and don't recommend you do either! Heck, I haven't used it aside from a few small tests, so use with caution.

The plugin is in its elementary stages, and doesn't handle scripting errors, task progress, task dependencies, task priorities, etc.


Requirements
------------

PHP needs to be configured with pcntl and shmop extensions, as it uses PHP_Fork to handle threading.

I have only tested it on PHP5. Please let me know if you get it working on PHP4.


Windows Compatibility
---------------------

Although the pcntl and shmop extensions don't run on Windows, I have added "linear threading" (ie. no threading) so you can test the functionality of your tasks on Windows.

