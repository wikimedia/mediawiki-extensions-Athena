--
-- Table structure for table `athena_probability`
--

CREATE TABLE /*_*/athena_probability (
  `ap_id` int(10) unsigned PRIMARY KEY auto_increment,
  `ap_variable` varbinary(255) NOT NULL default '',
  `ap_variable_not` tinyint(1) NOT NULL default 0,
  `ap_given` varbinary(255) default '',
  `ap_given_not` tinyint(1) NOT NULL default 0,
  `ap_value` double unsigned NOT NULL default 0.01,
  `ap_updated` datetime NOT NULL default CURRENT_TIMESTAMP
);

--
-- Table structure for table `athena_weighting`
--

CREATE TABLE athena_weighting (
  `aw_id` int(10) unsigned PRIMARY KEY auto_increment,
  `aw_variable` varbinary(255) NOT NULL default '',
  `aw_value` double unsigned NOT NULL default 0.1,
  `aw_updated` datetime NOT NULL default CURRENT_TIMESTAMP
);