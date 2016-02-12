CREATE TABLE /*_*/gwl_watchlist (
  `wid` int(14) NOT NULL AUTO_INCREMENT,
  `global_id` int(14) NOT NULL,
  `site_key` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `list` mediumblob NOT NULL,
  PRIMARY KEY (`wid`)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/global_id_site_key ON /*_*/gwl_watchlist (global_id, site_key);
