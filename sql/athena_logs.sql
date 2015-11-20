--
-- Table structure for table `athena_success_log`
--

CREATE TABLE athena_success_log (
  `page_id` int(10) unsigned PRIMARY KEY,
  `asl_value` double NOT NULL,
  `asl_user_age` int(11)
);

--
-- Table structure for table `athena_fail_log`
--

CREATE TABLE athena_fail_log (
  `afl_id` int(10) unsigned PRIMARY KEY auto_increment,
  `afl_value` double NOT NULL,
  `afl_user_age` int(11)
);

--
-- Table structure for table `athena_fail_page`
--

CREATE TABLE athena_fail_page (
  `afl_id` int(10) unsigned PRIMARY KEY auto_increment,
  `afp_namespace` int(11) NOT NULL,
  `afp_title` varbinary(255) NOT NULL,
  `afp_content` mediumblob NOT NULL,
  `afp_comment` tinyblob,
  `afp_user` int(10) unsigned NOT NULL default 0,
  `afp_timestamp` binary(14),
  `afp_sha1` varbinary(32)
);
