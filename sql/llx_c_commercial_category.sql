CREATE TABLE IF NOT EXISTS `llx_c_commercial_category` (
  `rowid` integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
  `entity` integer NOT NULL DEFAULT '1',
  `code` varchar(50) NOT NULL,
  `label` varchar(255) NOT NULL,
  `active` tinyint NOT NULL DEFAULT '1'
) ENGINE=innodb DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
