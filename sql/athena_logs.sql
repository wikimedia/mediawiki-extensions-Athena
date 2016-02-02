--
-- Table structure for table `athena_log`
--

CREATE TABLE athena_log (
  `al_id` int(10) unsigned PRIMARY KEY auto_increment,
  `al_value` double unsigned NOT NULL,
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
  `apd_timestamp` datetime NOT NULL default CURRENT_TIMESTAMP,
  `apd_language` tinyblob NOT NULL,
  `page_id` int(10) unsigned,
  `rev_id` int(10) unsigned
);

--
-- Table structure for table `athena_calculations`
--

CREATE TABLE athena_calculations (
  `al_id` int(10) unsigned PRIMARY KEY,
  `ac_p_diff_lang` double unsigned NOT NULL,
  `ac_w_diff_lang` double unsigned NOT NULL,
  `ac_p_deleted` double unsigned NOT NULL,
  `ac_w_deleted` double unsigned NOT NULL,
  `ac_p_wanted` double unsigned NOT NULL,
  `ac_w_wanted` double unsigned NOT NULL,
  `ac_p_user_age` double unsigned NOT NULL,
  `ac_w_user_age` double unsigned NOT NULL,
  `ac_p_title_length` double unsigned NOT NULL,
  `ac_w_title_length` double unsigned NOT NULL,
  `ac_p_namespace` double unsigned NOT NULL,
  `ac_w_namespace` double unsigned NOT NULL,
  `ac_p_syntax` double unsigned NOT NULL,
  `ac_w_syntax` double unsigned NOT NULL,
  `ac_p_link` double unsigned NOT NULL,
  `ac_w_link` double unsigned NOT NULL
);
