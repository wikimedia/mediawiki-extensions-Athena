--
-- Table structure for table `athena_stats`
--

CREATE TABLE athena_stats (
  `as_id` int(10) unsigned PRIMARY KEY auto_increment,
  `as_name` varbinary(255) NOT NULL default '',
  `as_value` integer unsigned NOT NULL default 0,
  `as_updated` datetime NOT NULL default CURRENT_TIMESTAMP
);