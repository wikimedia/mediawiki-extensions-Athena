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

--
-- Table structure for table `athena_calculations`
--

CREATE TABLE athena_calculations (
  `al_id` int(10) unsigned PRIMARY KEY,
  `ac_p_spam` double unsigned NOT NULL,
  `ac_p_lang` double unsigned NOT NULL,
  `ac_p_langandspam` double unsigned NOT NULL,
  `ac_p_langgivenspam` double unsigned NOT NULL,
  `ac_p_deleted` double unsigned NOT NULL,
  `ac_p_deletedandspam` double unsigned NOT NULL,
  `ac_p_deletedgivenspam` double unsigned NOT NULL,
  `ac_p_wanted` double unsigned NOT NULL,
  `ac_p_wantedandspam` double unsigned NOT NULL,
  `ac_p_wantedgivenspam` double unsigned NOT NULL,
  `ac_p_user` double unsigned NOT NULL,
  `ac_p_userandspam` double unsigned NOT NULL,
  `ac_p_usergivenspam` double unsigned NOT NULL,
  `ac_p_titlelength` double unsigned NOT NULL,
  `ac_p_titlelengthandspam` double unsigned NOT NULL,
  `ac_p_titlelengthgivenspam` double unsigned NOT NULL,
  `ac_p_namespace` double unsigned NOT NULL,
  `ac_p_namespaceandspam` double unsigned NOT NULL,
  `ac_p_namespacegivenspam` double unsigned NOT NULL,
  `ac_p_syntax` double unsigned NOT NULL,
  `ac_p_syntaxandspam` double unsigned NOT NULL,
  `ac_p_syntaxgivenspam` double unsigned NOT NULL,
  `ac_p_links` double unsigned NOT NULL,
  `ac_p_linksandspam` double unsigned NOT NULL,
  `ac_p_linksgivenspam` double unsigned NOT NULL,
  `ac_p_not_spam` double unsigned NOT NULL,
  `ac_p_langandnotspam` double unsigned NOT NULL,
  `ac_p_langgivennotspam` double unsigned NOT NULL,
  `ac_p_deletedandnotspam` double unsigned NOT NULL,
  `ac_p_deletedgivennotspam` double unsigned NOT NULL,
  `ac_p_wantedandnotspam` double unsigned NOT NULL,
  `ac_p_wantedgivennotspam` double unsigned NOT NULL,
  `ac_p_userandnotspam` double unsigned NOT NULL,
  `ac_p_usergivennotspam` double unsigned NOT NULL,
  `ac_p_titlelengthandnotspam` double unsigned NOT NULL,
  `ac_p_titlelengthgivennotspam` double unsigned NOT NULL,
  `ac_p_namespaceandnotspam` double unsigned NOT NULL,
  `ac_p_namespacegivennotspam` double unsigned NOT NULL,
  `ac_p_syntaxandnotspam` double unsigned NOT NULL,
  `ac_p_syntaxgivennotspam` double unsigned NOT NULL,
  `ac_p_linksandnotspam` double unsigned NOT NULL,
  `ac_p_linksgivennotspam` double unsigned NOT NULL
);

--
-- Table structure for table `athena_stats`
--
CREATE TABLE athena_stats (
  `as_id` int(10) unsigned PRIMARY KEY auto_increment,
  `as_name` varbinary(255) NOT NULL default '',
  `as_value` integer unsigned NOT NULL default 0,
  `as_updated` timestamp NOT NULL default CURRENT_TIMESTAMP
);

INSERT INTO `athena_stats` (`as_id`, `as_name`, `as_value`, `as_updated`) VALUES
(1, 'pages', 0, '1970-01-01 00:00:01'),
(2, 'spam', 0, '1970-01-01 00:00:01'),
(3, 'notspam', 0, '1970-01-01 00:00:01'),
(4, 'difflang', 0, '1970-01-01 00:00:01'),
(5, 'samelang', 0, '1970-01-01 00:00:01'),
(6, 'deleted', 0, '1970-01-01 00:00:01'),
(7, 'notdeleted', 0, '1970-01-01 00:00:01'),
(8, 'wanted', 0, '1970-01-01 00:00:01'),
(9, 'notwanted', 0, '1970-01-01 00:00:01'),
(10, 'userother', 0, '1970-01-01 00:00:01'),
(11, 'anon', 0, '1970-01-01 00:00:01'),
(12, 'user1', 0, '1970-01-01 00:00:01'),
(13, 'user5', 0, '1970-01-01 00:00:01'),
(14, 'user30', 0, '1970-01-01 00:00:01'),
(15, 'user60', 0, '1970-01-01 00:00:01'),
(16, 'user12', 0, '1970-01-01 00:00:01'),
(17, 'user24', 0, '1970-01-01 00:00:01'),
(18, 'titlelength', 0, '1970-01-01 00:00:01'),
(19, 'nottitlelength', 0, '1970-01-01 00:00:01'),
(20, 'nsmain', 0, '1970-01-01 00:00:01'),
(21, 'nstalk', 0, '1970-01-01 00:00:01'),
(22, 'nsuser', 0, '1970-01-01 00:00:01'),
(23, 'nsusertalk', 0, '1970-01-01 00:00:01'),
(24, 'nsother', 0, '1970-01-01 00:00:01'),
(25, 'syntaxnone', 0, '1970-01-01 00:00:01'),
(26, 'syntaxbasic', 0, '1970-01-01 00:00:01'),
(27, 'syntaxcomplex', 0, '1970-01-01 00:00:01'),
(28, 'brokenspambot', 0, '1970-01-01 00:00:01'),
(29, 'links0', 0, '1970-01-01 00:00:01'),
(30, 'links5', 0, '1970-01-01 00:00:01'),
(31, 'links20', 0, '1970-01-01 00:00:01'),
(32, 'links50', 0, '1970-01-01 00:00:01'),
(33, 'spamanddifflang', 0, '1970-01-01 00:00:01'),
(34, 'spamandsamelang', 0, '1970-01-01 00:00:01'),
(35, 'spamanddeleted', 0, '1970-01-01 00:00:01'),
(36, 'spamandnotdeleted', 0, '1970-01-01 00:00:01'),
(37, 'spamandwanted', 0, '1970-01-01 00:00:01'),
(38, 'spamandnotwanted', 0, '1970-01-01 00:00:01'),
(39, 'spamanduserother', 0, '1970-01-01 00:00:01'),
(40, 'spamandanon', 0, '1970-01-01 00:00:01'),
(41, 'spamanduser1', 0, '1970-01-01 00:00:01'),
(42, 'spamanduser5', 0, '1970-01-01 00:00:01'),
(43, 'spamanduser30', 0, '1970-01-01 00:00:01'),
(44, 'spamanduser60', 0, '1970-01-01 00:00:01'),
(45, 'spamanduser12', 0, '1970-01-01 00:00:01'),
(46, 'spamanduser24', 0, '1970-01-01 00:00:01'),
(47, 'spamandtitlelength', 0, '1970-01-01 00:00:01'),
(48, 'spamandnottitlelength', 0, '1970-01-01 00:00:01'),
(49, 'spamandnsmain', 0, '1970-01-01 00:00:01'),
(50, 'spamandnstalk', 0, '1970-01-01 00:00:01'),
(51, 'spamandnsuser', 0, '1970-01-01 00:00:01'),
(52, 'spamandnsusertalk', 0, '1970-01-01 00:00:01'),
(53, 'spamandnsother', 0, '1970-01-01 00:00:01'),
(54, 'spamandsyntaxnone', 0, '1970-01-01 00:00:01'),
(55, 'spamandsyntaxbasic', 0, '1970-01-01 00:00:01'),
(56, 'spamandsyntaxcomplex', 0, '1970-01-01 00:00:01'),
(57, 'spamandbrokenspambot', 0, '1970-01-01 00:00:01'),
(58, 'spamandlinks0', 0, '1970-01-01 00:00:01'),
(59, 'spamandlinks5', 0, '1970-01-01 00:00:01'),
(60, 'spamandlinks20', 0, '1970-01-01 00:00:01'),
(61, 'spamandlinks50', 0, '1970-01-01 00:00:01'),
(62, 'nsfile', 0, '1970-01-01 00:00:01'),
(63, 'spamandnsfile', 0, '1970-01-01 00:00:01');
