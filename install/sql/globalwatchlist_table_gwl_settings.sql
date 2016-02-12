CREATE TABLE /*_*/gwl_settings (
  `sid` int(14) NOT NULL AUTO_INCREMENT,
  `global_id` int(14) NOT NULL,
  `site_keys` mediumblob NOT NULL,
  PRIMARY KEY (`sid`)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/global_id on /*_*/gwl_settings (global_id);
