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
-- Table structure for newlinks
-- ----------------------------
DROP TABLE IF EXISTS `newlinks`;
CREATE TABLE `newlinks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL,
  `level` int(11) NOT NULL,
  PRIMARY KEY (`id`)
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
) ENGINE=InnoDB AUTO_INCREMENT=0;

-- ----------------------------
-- Procedure structure for AddLinkToQueue
-- ----------------------------
DROP PROCEDURE IF EXISTS `AddLinkToQueue`;
DELIMITER ;;
CREATE DEFINER=`root`@`%` PROCEDURE `AddLinkToQueue`(IN id_in INT,IN url_in VARCHAR(255),IN level_in INT)
BEGIN
	DECLARE c INT DEFAULT 0;
	DECLARE done INT DEFAULT 0;
	DECLARE tid INT;
	DECLARE turl VARCHAR(255);
	DECLARE tlevel INT;
	DECLARE rs CURSOR FOR SELECT id,url,`level` FROM taskqueue t where t.url=url_in limit 1;
	DECLARE CONTINUE HANDLER FOR SQLSTATE '02000' SET done = 1;

	-- 删除新链接表中的记录
	DELETE FROM newlinks WHERE id=id_in;

	-- 排除正在处理的链接
	IF c=0 THEN
		SELECT count(*) INTO c FROM onprocess o where o.url=url_in;
	END IF;

	-- 排除标记为不更新的链接
	IF c=0 THEN
		SELECT count(*) INTO c FROM notupdate n where n.url=url_in;
	END IF;

	-- 排除标记为错误的链接
	IF c=0 THEN
		SELECT count(*) INTO c FROM errortask e where e.url=url_in;
	END IF;

	-- 判断链接是否存在于队列
	IF c=0 THEN
		OPEN rs;
		FETCH NEXT FROM rs INTO tid,turl,tlevel;
		IF NOT done THEN
			SET c=1;
			IF tlevel<level_in THEN
				UPDATE taskqueue SET `level`=level_in where id=tid LIMIT 1;
			END IF;
		END IF;
		CLOSE rs;
	END IF;

	-- 添加链接到队列
	IF c=0 THEN
		INSERT INTO taskqueue VALUES(null,url_in,level_in,(SELECT unix_timestamp(now())),0);
	END IF;
END
;;
DELIMITER ;
