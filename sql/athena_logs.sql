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
(1, 'pages', 0, '0000-00-00 00:00:00'),
(2, 'spam', 0, '0000-00-00 00:00:00'),
(3, 'notspam', 0, '0000-00-00 00:00:00'),
(4, 'difflang', 0, '0000-00-00 00:00:00'),
(5, 'samelang', 0, '0000-00-00 00:00:00'),
(6, 'deleted', 0, '0000-00-00 00:00:00'),
(7, 'notdeleted', 0, '0000-00-00 00:00:00'),
(8, 'wanted', 0, '0000-00-00 00:00:00'),
(9, 'notwanted', 0, '0000-00-00 00:00:00'),
(10, 'userother', 0, '0000-00-00 00:00:00'),
(11, 'anon', 0, '0000-00-00 00:00:00'),
(12, 'user1', 0, '0000-00-00 00:00:00'),
(13, 'user5', 0, '0000-00-00 00:00:00'),
(14, 'user30', 0, '0000-00-00 00:00:00'),
(15, 'user60', 0, '0000-00-00 00:00:00'),
(16, 'user12', 0, '0000-00-00 00:00:00'),
(17, 'user24', 0, '0000-00-00 00:00:00'),
(18, 'titlelength', 0, '0000-00-00 00:00:00'),
(19, 'nottitlelength', 0, '0000-00-00 00:00:00'),
(20, 'nsmain', 0, '0000-00-00 00:00:00'),
(21, 'nstalk', 0, '0000-00-00 00:00:00'),
(22, 'nsuser', 0, '0000-00-00 00:00:00'),
(23, 'nsusertalk', 0, '0000-00-00 00:00:00'),
(24, 'nsother', 0, '0000-00-00 00:00:00'),
(25, 'syntaxnone', 0, '0000-00-00 00:00:00'),
(26, 'syntaxbasic', 0, '0000-00-00 00:00:00'),
(27, 'syntaxcomplex', 0, '0000-00-00 00:00:00'),
(28, 'brokenspambot', 0, '0000-00-00 00:00:00'),
(29, 'links0', 0, '0000-00-00 00:00:00'),
(30, 'links5', 0, '0000-00-00 00:00:00'),
(31, 'links20', 0, '0000-00-00 00:00:00'),
(32, 'links50', 0, '0000-00-00 00:00:00'),
(33, 'spamanddifflang', 0, '0000-00-00 00:00:00'),
(34, 'spamandsamelang', 0, '0000-00-00 00:00:00'),
(35, 'spamanddeleted', 0, '0000-00-00 00:00:00'),
(36, 'spamandnotdeleted', 0, '0000-00-00 00:00:00'),
(37, 'spamandwanted', 0, '0000-00-00 00:00:00'),
(38, 'spamandnotwanted', 0, '0000-00-00 00:00:00'),
(39, 'spamanduserother', 0, '0000-00-00 00:00:00'),
(40, 'spamandanon', 0, '0000-00-00 00:00:00'),
(41, 'spamanduser1', 0, '0000-00-00 00:00:00'),
(42, 'spamanduser5', 0, '0000-00-00 00:00:00'),
(43, 'spamanduser30', 0, '0000-00-00 00:00:00'),
(44, 'spamanduser60', 0, '0000-00-00 00:00:00'),
(45, 'spamanduser12', 0, '0000-00-00 00:00:00'),
(46, 'spamanduser24', 0, '0000-00-00 00:00:00'),
(47, 'spamandtitlelength', 0, '0000-00-00 00:00:00'),
(48, 'spamandnottitlelength', 0, '0000-00-00 00:00:00'),
(49, 'spamandnsmain', 0, '0000-00-00 00:00:00'),
(50, 'spamandnstalk', 0, '0000-00-00 00:00:00'),
(51, 'spamandnsuser', 0, '0000-00-00 00:00:00'),
(52, 'spamandnsusertalk', 0, '0000-00-00 00:00:00'),
(53, 'spamandnsother', 0, '0000-00-00 00:00:00'),
(54, 'spamandsyntaxnone', 0, '0000-00-00 00:00:00'),
(55, 'spamandsyntaxbasic', 0, '0000-00-00 00:00:00'),
(56, 'spamandsyntaxcomplex', 0, '0000-00-00 00:00:00'),
(57, 'spamandbrokenspambot', 0, '0000-00-00 00:00:00'),
(58, 'spamandlinks0', 0, '0000-00-00 00:00:00'),
(59, 'spamandlinks5', 0, '0000-00-00 00:00:00'),
(60, 'spamandlinks20', 0, '0000-00-00 00:00:00'),
(61, 'spamandlinks50', 0, '0000-00-00 00:00:00'),
(62, 'nsfile', 0, '0000-00-00 00:00:00'),
(63, 'spamandnsfile', 0, '0000-00-00 00:00:00');
