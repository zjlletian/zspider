/*
Navicat MySQL Data Transfer

Source Server         : zhou-vm
Source Server Version : 50629
Source Host           : 192.168.1.105:3306
Source Database       : zspider

Target Server Type    : MYSQL
Target Server Version : 50629
File Encoding         : 65001

Date: 2016-02-26 16:00:39
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for errortask
-- ----------------------------
DROP TABLE IF EXISTS `errortask`;
CREATE TABLE `errortask` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) CHARACTER SET latin1 NOT NULL,
  `time` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url_unique` (`url`) USING BTREE,
  KEY `url_sort` (`url`) USING BTREE,
  KEY `time_sort` (`time`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=3373 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for newlinks
-- ----------------------------
DROP TABLE IF EXISTS `newlinks`;
CREATE TABLE `newlinks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL,
  `level` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `url_sort` (`url`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=2385198 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for notupdate
-- ----------------------------
DROP TABLE IF EXISTS `notupdate`;
CREATE TABLE `notupdate` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url_unique` (`url`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=103 DEFAULT CHARSET=utf8;

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
  UNIQUE KEY `url_unique` (`url`),
  KEY `uniqid_sort` (`uniqid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=46759 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for spiders
-- ----------------------------
DROP TABLE IF EXISTS `spiders`;
CREATE TABLE `spiders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `ip` varchar(64) NOT NULL DEFAULT '',
  `acktime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_unique` (`ip`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=355 DEFAULT CHARSET=utf8;

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
  UNIQUE KEY `url_unique` (`url`) USING BTREE,
  KEY `time_sort` (`time`) USING BTREE,
  KEY `type_sort` (`type`) USING BTREE,
  KEY `url_sort` (`url`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1539495 DEFAULT CHARSET=utf8;
