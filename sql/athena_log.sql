--
-- Table structure for table `athena_log`
--

CREATE TABLE athena_log (
  `al_id` int(10) unsigned PRIMARY KEY auto_increment,
  `al_value` double NOT NULL,
  `al_success` tinyint(1) unsigned NOT NULL default 0,
  `al_user_age` int(11),
  `al_links` double unsigned,
  `al_link_percentage` double unsigned,
  `al_syntax` double unsigned,
  `al_language` tinyint(1) unsigned,
  `al_wanted` tinyint(1) unsigned,
  `al_deleted` tinyint(1) unsigned,
  `al_overridden` tinyint(1) unsigned NOT NULL default 0
);
