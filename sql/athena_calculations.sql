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
