--
-- Table structure for table `athena_probability`
--

CREATE TABLE /*_*/athena_probability (
  `ap_id` int(11) PRIMARY KEY auto_increment,
  `ap_variable` varchar(255) NOT NULL default '',
  `ap_given` varchar(255) default '',
  `ap_value` decimal NOT NULL default 0.01,
  `ap_updated` datetime NOT NULL,
); /*$wgDBTableOptions*/
/*CREATE INDEX /*i*/ap_id ON      /*_*/athena_probability (ap_id);*/
