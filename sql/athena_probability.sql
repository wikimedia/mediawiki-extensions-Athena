--
-- Table structure for table `athena_probability`
--

CREATE TABLE /*_*/athena_probability (
  `ap_id` int(11) PRIMARY KEY auto_increment,
  `ap_variable` VARCHAR(255) NOT NULL default '',
  `ap_given` varchar(255) default '',
  `ap_value` double NOT NULL default 0.01,
  `ap_updated` datetime NOT NULL default CURRENT_TIMESTAMP
) /*$wgDBTableOptions*/;
/*CREATE INDEX /*i*/ap_id ON      /*_*/athena_probability (ap_id);*/
