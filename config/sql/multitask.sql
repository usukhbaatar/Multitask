CREATE TABLE `multitask_queued_tasks` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `task` char(50) default NULL,
  `method` char(50) default NULL,
  `data` text,
  `status` tinyint(4) default '0',
  `created` datetime default NULL,
  `modified` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `status` (`status`)
);