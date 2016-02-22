/*
Navicat MySQL Data Transfer

Source Server         : zhoujunlong.dev
Source Server Version : 50173
Source Host           : zhoujunlong.dev:3306
Source Database       : zspider

Target Server Type    : MYSQL
Target Server Version : 50173
File Encoding         : 65001

Date: 2016-02-22 10:34:56
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for notupdate
-- ----------------------------
DROP TABLE IF EXISTS `notupdate`;
CREATE TABLE `notupdate` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url_unique` (`url`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for onprocess
-- ----------------------------
DROP TABLE IF EXISTS `onprocess`;
CREATE TABLE `onprocess` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL,
  `level` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `acktime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url_unique` (`url`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;

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
  KEY `time_sort` (`time`) USING BTREE
  KEY `type_sort` (`type`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;
