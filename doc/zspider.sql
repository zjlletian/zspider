SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for errortask
-- ----------------------------
DROP TABLE IF EXISTS `errortask`;
CREATE TABLE `errortask` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`) USING BTREE,
  KEY `time` (`time`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for notupdate
-- ----------------------------
DROP TABLE IF EXISTS `notupdate`;
CREATE TABLE `notupdate` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for onprocess
-- ----------------------------
DROP TABLE IF EXISTS `onprocess`;
CREATE TABLE `onprocess` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniqid` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `level` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `proctime` int(11) NOT NULL,
  `acktime` int(11) NOT NULL,
  `times` int(11) NOT NULL DEFAULT '1',
  `status` int(11) NOT NULL DEFAULT '0',
  `spider` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`) USING BTREE,
  KEY `uniqid` (`uniqid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for queueinfo
-- ----------------------------
DROP TABLE IF EXISTS `queueinfo`;
CREATE TABLE `queueinfo` (
  `item` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  UNIQUE KEY `key` (`item`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for spiders
-- ----------------------------
DROP TABLE IF EXISTS `spiders`;
CREATE TABLE `spiders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `ip` varchar(64) NOT NULL DEFAULT '',
  `acktime` int(11) NOT NULL,
  `handler` int(11) NOT NULL DEFAULT '0',
  `sysload` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for taskqueue
-- ----------------------------
DROP TABLE IF EXISTS `taskqueue`;
CREATE TABLE `taskqueue` (
  `id` int(32) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL,
  `level` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`) USING BTREE,
  KEY `type_time` (`type`,`time`) USING BTREE,
  KEY `time` (`time`) USING BTREE,
  KEY `type` (`type`) USING HASH
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
