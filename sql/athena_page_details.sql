--
-- Table structure for table `athena_page_details`
--

CREATE TABLE athena_page_details (
  `al_id` int(10) unsigned PRIMARY KEY,
  `apd_namespace` int(11) NOT NULL,
  `apd_title` varbinary(255) NOT NULL,
  `apd_content` mediumblob NOT NULL,
  `apd_comment` tinyblob,
  `apd_user` int(10) unsigned NOT NULL default 0,
  `apd_timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `apd_language` varbinary(255),
  `page_id` int(10) unsigned,
  `rev_id` int(10) unsigned
);
