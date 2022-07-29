/*
Navicat MySQL Data Transfer

Source Server         : local
Source Server Version : 50726
Source Host           : localhost:3306
Source Database       : laraveldemo

Target Server Type    : MYSQL
Target Server Version : 50726
File Encoding         : 65001

Date: 2020-05-14 11:26:29
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for app_admin_accounts
-- ----------------------------
DROP TABLE IF EXISTS `app_admin_accounts`;
CREATE TABLE `app_admin_accounts` (
  `id` int(10) NOT NULL AUTO_INCREMENT COMMENT '超管ID主键',
  `username` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '用户名',
  `country_id` int(11) DEFAULT NULL,
  `password` varchar(64) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '密码',
  `is_super_admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为超级管理员1为普通限制管理员，2为超级管理员',
  `portRaitUri` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'storage/img/defaultlogo.png' COMMENT '用户头像',
  `sex` tinyint(1) DEFAULT '3' COMMENT '1为男2为女3为保密',
  `birthday` timestamp NULL DEFAULT NULL COMMENT '用户生日',
  `phone_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '手机状态:1为未认证，2未已认证',
  `email_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '邮箱状态:1为未认证，2未已认证',
  `payment_password` varchar(64) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '支付密码',
  `actual_name` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '真实姓名',
  `phone_number` varchar(20) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '手机号码',
  `email` varchar(100) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '邮箱地址',
  `company_type` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '企业行业类型',
  `address` varchar(50) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '详细地址',
  `longitude` varchar(30) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '经度',
  `latitude` varchar(30) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '纬度',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态;0.禁用;1.启用;',
  `last_ip` varchar(20) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'ip地址',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `area` varchar(10) CHARACTER SET utf8 NOT NULL DEFAULT '86' COMMENT '区号',
  `level` tinyint(1) NOT NULL DEFAULT '2' COMMENT '商家等级权限',
  `pin` varchar(64) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '商家pin码',
  `btc_balance` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT 'btc余额',
  `rpz_balance` decimal(20,8) NOT NULL DEFAULT '0.00000000' COMMENT 'rpz余额',
  `remember_token` varchar(64) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT 'token',
  `btc_self_address` varchar(64) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '私人btc提现地址',
  `rpz_self_address` varchar(64) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '私人rpz提现地址',
  `easemob_u` varchar(20) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '环信账号',
  `easemob_p` varchar(32) CHARACTER SET utf8 NOT NULL DEFAULT '' COMMENT '环信密码',
  `language` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cn' COMMENT '用户使用语言（en英语，cn简体中文，hk繁体中文，jp日文，kr韩文，th泰文）',
  `last_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `admin_system_accounts_username_unique` (`username`) USING BTREE,
  KEY `created_at_index` (`created_at`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='超级管理员表';

-- ----------------------------
-- Records of app_admin_accounts
-- ----------------------------

-- ----------------------------
-- Table structure for app_admin_config
-- ----------------------------
DROP TABLE IF EXISTS `app_admin_config`;
CREATE TABLE `app_admin_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` int(1) NOT NULL DEFAULT '1' COMMENT '类型',
  `name` varchar(50) NOT NULL COMMENT '配置的名称',
  `description` varchar(255) NOT NULL COMMENT '配置的描述',
  `value` varchar(100) NOT NULL COMMENT '配置的值',
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '添加或者更新时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Records of app_admin_config
-- ----------------------------
INSERT INTO `app_admin_config` VALUES ('1', '1', 'news_free_times', '每天免费推送消息条数', '5', '2019-06-23 18:34:09');
INSERT INTO `app_admin_config` VALUES ('2', '1', 'move', '每天发红包/聊天转账的限额（单位：美元）', '10000', '2019-05-06 17:23:27');
INSERT INTO `app_admin_config` VALUES ('4', '1', 'phone_email', '手机充值服务电子邮件', '1003492117@qq.com', '2019-04-28 20:24:21');
INSERT INTO `app_admin_config` VALUES ('5', '1', 'pos_email', 'pos获取邮箱', '1003492117@qq.com', '2018-12-25 10:34:08');
INSERT INTO `app_admin_config` VALUES ('6', '1', 'android_download_url', '安卓下载链接', 'https://www.pgyer.com/5xvD', '2018-12-28 14:57:34');
INSERT INTO `app_admin_config` VALUES ('7', '1', 'ios_download_url', '苹果下载链接', 'https://www.pgyer.com/26Xj', '2018-12-28 14:58:09');
INSERT INTO `app_admin_config` VALUES ('8', '0', 'red_packet_max_num', '红包最大数量', '500', '2019-04-28 20:21:41');
INSERT INTO `app_admin_config` VALUES ('11', '0', 'is_business_red_packet', '商家是否可领红包（0否1是）', '0', '2018-12-25 09:23:27');
INSERT INTO `app_admin_config` VALUES ('12', '1', 'share_url', '邀请链接', 'https://user.chain-chat.app/client/share/share.html', '2019-10-15 08:43:33');
INSERT INTO `app_admin_config` VALUES ('13', '1', 'invite_url_cn', '中文邀请链接', 'https://user.chain-chat.app/client/share/invite-cn.html', '2019-10-11 01:47:50');
INSERT INTO `app_admin_config` VALUES ('14', '1', 'invite_url_en', '英文邀请链接', 'https://user.chain-chat.app/client/share/invite-en.html', '2019-10-11 01:47:53');
INSERT INTO `app_admin_config` VALUES ('15', '1', 'default_background', '优惠券默认背景', 'storage/img/commodity_type/keyong.png', '2019-04-18 14:58:05');
INSERT INTO `app_admin_config` VALUES ('16', '1', 'unavailable_background', '不可用优惠券背景', 'storage/img/commodity_type/bukeyong.png', '2019-04-18 14:58:00');
INSERT INTO `app_admin_config` VALUES ('17', '1', 'exchange_points_one', '单笔转换points不能超过(单位：美元)', '500', '2019-04-23 15:16:56');
INSERT INTO `app_admin_config` VALUES ('18', '1', 'exchange_points_today', '单日转换points不能超过(单位：美元)', '5000', '2019-04-23 15:17:32');
INSERT INTO `app_admin_config` VALUES ('19', '1', 'invite_integral', '邀请好友获得的积分', '100', '2019-04-26 03:31:47');
INSERT INTO `app_admin_config` VALUES ('20', '1', 'coupon_activity', '每日优惠券活动', '0', '2019-05-13 11:21:12');
INSERT INTO `app_admin_config` VALUES ('21', '1', 'invite_url_hk', '繁体中文邀请链接', 'https://mobile.chainchat.io/client/share/invite-hk.html', '2019-04-28 18:12:49');
INSERT INTO `app_admin_config` VALUES ('22', '1', 'move_max_money', '单个红包/转账最大金额（单位：美元）', '500', '2019-05-06 17:23:23');
INSERT INTO `app_admin_config` VALUES ('23', '1', 'exchange_points_percentage', '锐点兑换百分比', '15', '2019-06-25 04:40:25');

-- ----------------------------
-- Table structure for app_admin_log_logins
-- ----------------------------
DROP TABLE IF EXISTS `app_admin_log_logins`;
CREATE TABLE `app_admin_log_logins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL COMMENT '用户ID',
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT '用户名称',
  `ip` char(20) COLLATE utf8_unicode_ci NOT NULL COMMENT '用户IP地址',
  `path` varchar(50) CHARACTER SET utf8 NOT NULL COMMENT '操作方法',
  `type` char(10) COLLATE utf8_unicode_ci NOT NULL COMMENT '事件类型',
  `desc` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT '单设备登录',
  `redistime` varchar(50) COLLATE utf8_unicode_ci NOT NULL COMMENT '存储在redis的时间戳',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=4564 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Records of app_admin_log_logins
-- ----------------------------

-- ----------------------------
-- Table structure for app_img_captcha
-- ----------------------------
DROP TABLE IF EXISTS `app_img_captcha`;
CREATE TABLE `app_img_captcha` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `img_background` varchar(255) NOT NULL DEFAULT '' COMMENT '背景图',
  `img_small` varchar(255) NOT NULL DEFAULT '' COMMENT '抠出图片',
  `x` varchar(15) NOT NULL DEFAULT '0' COMMENT 'x轴数据',
  `y` varchar(15) NOT NULL DEFAULT '0' COMMENT 'Y轴数据',
  `creation_at` datetime NOT NULL COMMENT '添加时间',
  `updated_at` datetime NOT NULL COMMENT '更新时间',
  `captcha` tinyint(4) NOT NULL DEFAULT '0' COMMENT '验证状态,0为未使用，1验证成功，2验证失败，3过期',
  `email_captcha` tinyint(4) NOT NULL DEFAULT '0' COMMENT '邮箱验证状态,0为未使用，1为已使用',
  `mobile_captcha` tinyint(4) NOT NULL DEFAULT '0' COMMENT '手机验证状态',
  `reg_captcha` tinyint(4) NOT NULL DEFAULT '0' COMMENT '注册验证,0为未使用，1为已使用',
  `reset_captcha` tinyint(4) NOT NULL DEFAULT '0' COMMENT '重置密码验证,0为未使用，1为已使用',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4021 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Records of app_img_captcha
-- ----------------------------
INSERT INTO `app_img_captcha` VALUES ('4018', 'E:\\wamp\\www\\car\\public\\captcha/banner.png', 'E:\\wamp\\www\\car\\public\\captcha/BoU8xYMpsJuhPX0ocyhKmTh3n5pXNmtXrRKLN4T2.png', '191', '95', '2019-11-28 10:02:50', '2019-11-28 10:02:50', '0', '0', '0', '0', '0');
INSERT INTO `app_img_captcha` VALUES ('4019', 'E:\\wamp\\www\\car\\public\\captcha/banner.png', 'E:\\wamp\\www\\car\\public\\captcha/BoU8xYMpsJuhPX0ocyhKmTh3n5pXNmtXrRKLN4T2.png', '150', '123', '2019-11-28 10:10:05', '2019-11-28 10:15:13', '3', '0', '0', '0', '0');
INSERT INTO `app_img_captcha` VALUES ('4020', 'E:\\wamp\\www\\car\\public\\captcha/banner.png', 'E:\\wamp\\www\\car\\public\\captcha/BoU8xYMpsJuhPX0ocyhKmTh3n5pXNmtXrRKLN4T2.png', '172', '76', '2019-11-29 06:34:02', '2019-11-29 06:34:02', '0', '0', '0', '0', '0');

-- ----------------------------
-- Table structure for app_login_token
-- ----------------------------
DROP TABLE IF EXISTS `app_login_token`;
CREATE TABLE `app_login_token` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) NOT NULL COMMENT '用户ID',
  `tokentext` text CHARACTER SET utf8 COLLATE utf8_unicode_ci COMMENT 'token里面的内容',
  `token` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'token',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '类型:0=老token,1=新token',
  `token_sigle` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '单点登陆token',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `car_login_token_index` (`tokentext`(255)) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Records of app_login_token
-- ----------------------------
INSERT INTO `app_login_token` VALUES ('1', '3', 'a:4:{s:3:\"uid\";s:1:\"3\";s:1:\"t\";i:1589426779;s:2:\"nt\";s:0:\"\";s:2:\"ot\";s:0:\"\";}', '4aef8e2c51016f3249560be8d661855320f1da42', '0', '', '2019-11-29 03:13:22', '2020-05-14 03:26:19');
INSERT INTO `app_login_token` VALUES ('2', '3', 'a:4:{s:3:\"uid\";s:1:\"3\";s:1:\"t\";i:1575016469;s:2:\"nt\";s:0:\"\";s:2:\"ot\";s:40:\"cbacc8d2fde3ed8f45694caeaecf769df746723c\";}', 'dfa19900cfe1f5449af70059613b1f55b9ff451b', '1', '', '2019-11-29 08:34:29', '2019-11-29 08:34:29');

-- ----------------------------
-- Table structure for app_message
-- ----------------------------
DROP TABLE IF EXISTS `app_message`;
CREATE TABLE `app_message` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '表的id',
  `account_id` int(11) NOT NULL DEFAULT '1' COMMENT 'app_admin_accounts 表的id',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '消息标题',
  `content` text NOT NULL COMMENT '消息内容',
  `title_en` varchar(255) NOT NULL DEFAULT '',
  `content_en` text NOT NULL,
  `title_cn` varchar(255) NOT NULL DEFAULT '',
  `content_cn` text NOT NULL,
  `title_hk` varchar(255) NOT NULL DEFAULT '',
  `content_hk` text NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '消息类型，1 版本更新，2 活动通知， 3 通知，4 公告 ， 5 维护 , 6 广告,  7 轮播图',
  `lang` char(10) NOT NULL DEFAULT '' COMMENT '语言',
  `ios_version` varchar(30) NOT NULL DEFAULT '' COMMENT 'ios 版本',
  `android_version` varchar(30) NOT NULL DEFAULT '' COMMENT 'android版本',
  `ios_download` varchar(255) NOT NULL DEFAULT '' COMMENT 'ios下载地址',
  `android_download` varchar(255) NOT NULL DEFAULT '' COMMENT 'android 下载地址',
  `forced_update` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否强制更新， 0否，1是',
  `start_time` timestamp NULL DEFAULT NULL COMMENT '维护开始时间 type == 5',
  `maintian_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '维护类型，1 app ，2 商家， 3 app和商家同时维护',
  `is_send` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否发送了环信， 0 否 ， 1 是',
  `close` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否关闭， 0 否 ， 1 是',
  `end_time` timestamp NULL DEFAULT NULL COMMENT '维护结束时间 type == 5',
  `thumb` varchar(255) NOT NULL DEFAULT '' COMMENT '广告图片',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_del` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否删除 ， 0 否 ，1 是',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='用户系统消息表';

-- ----------------------------
-- Records of app_message
-- ----------------------------

-- ----------------------------
-- Table structure for app_message_read
-- ----------------------------
DROP TABLE IF EXISTS `app_message_read`;
CREATE TABLE `app_message_read` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL DEFAULT '0' COMMENT '系统消息message表的id',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '用户id',
  `read` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已读，0 否 ，1 是',
  `is_private` tinyint(1) NOT NULL DEFAULT '0' COMMENT '消息是否为私有:0 =后台发的公有,1=单独给自己的',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `message_id@uid` (`message_id`,`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='用户系统消息是否已读取表';

-- ----------------------------
-- Records of app_message_read
-- ----------------------------

-- ----------------------------
-- Table structure for app_migrations
-- ----------------------------
DROP TABLE IF EXISTS `app_migrations`;
CREATE TABLE `app_migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of app_migrations
-- ----------------------------
INSERT INTO `app_migrations` VALUES ('1', '2014_10_12_000000_create_users_table', '1');
INSERT INTO `app_migrations` VALUES ('2', '2014_10_12_100000_create_password_resets_table', '1');
INSERT INTO `app_migrations` VALUES ('3', '2016_06_01_000001_create_oauth_auth_codes_table', '1');
INSERT INTO `app_migrations` VALUES ('4', '2016_06_01_000002_create_oauth_access_tokens_table', '1');
INSERT INTO `app_migrations` VALUES ('5', '2016_06_01_000003_create_oauth_refresh_tokens_table', '1');
INSERT INTO `app_migrations` VALUES ('6', '2016_06_01_000004_create_oauth_clients_table', '1');
INSERT INTO `app_migrations` VALUES ('7', '2016_06_01_000005_create_oauth_personal_access_clients_table', '1');

-- ----------------------------
-- Table structure for app_oauth_access_tokens
-- ----------------------------
DROP TABLE IF EXISTS `app_oauth_access_tokens`;
CREATE TABLE `app_oauth_access_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `app_oauth_access_tokens_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of app_oauth_access_tokens
-- ----------------------------

-- ----------------------------
-- Table structure for app_oauth_auth_codes
-- ----------------------------
DROP TABLE IF EXISTS `app_oauth_auth_codes`;
CREATE TABLE `app_oauth_auth_codes` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of app_oauth_auth_codes
-- ----------------------------

-- ----------------------------
-- Table structure for app_oauth_clients
-- ----------------------------
DROP TABLE IF EXISTS `app_oauth_clients`;
CREATE TABLE `app_oauth_clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `redirect` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `personal_access_client` tinyint(1) NOT NULL,
  `password_client` tinyint(1) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `app_oauth_clients_user_id_index` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of app_oauth_clients
-- ----------------------------
INSERT INTO `app_oauth_clients` VALUES ('1', null, 'Laravel Personal Access Client', '9zxWOJnHwd8EISJeip1FiqHJ4fbXrk7pB5YLjYaz', 'http://localhost', '1', '0', '0', '2019-11-29 08:04:36', '2019-11-29 08:04:36');
INSERT INTO `app_oauth_clients` VALUES ('2', null, 'Laravel Password Grant Client', '52s81b1YVpIaxm0vJ1DMrppbmFovJxyD0evZwUuL', 'http://localhost', '0', '1', '0', '2019-11-29 08:04:36', '2019-11-29 08:04:36');

-- ----------------------------
-- Table structure for app_oauth_personal_access_clients
-- ----------------------------
DROP TABLE IF EXISTS `app_oauth_personal_access_clients`;
CREATE TABLE `app_oauth_personal_access_clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `app_oauth_personal_access_clients_client_id_index` (`client_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of app_oauth_personal_access_clients
-- ----------------------------
INSERT INTO `app_oauth_personal_access_clients` VALUES ('1', '1', '2019-11-29 08:04:36', '2019-11-29 08:04:36');

-- ----------------------------
-- Table structure for app_oauth_refresh_tokens
-- ----------------------------
DROP TABLE IF EXISTS `app_oauth_refresh_tokens`;
CREATE TABLE `app_oauth_refresh_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_token_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `app_oauth_refresh_tokens_access_token_id_index` (`access_token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of app_oauth_refresh_tokens
-- ----------------------------

-- ----------------------------
-- Table structure for app_password_resets
-- ----------------------------
DROP TABLE IF EXISTS `app_password_resets`;
CREATE TABLE `app_password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `app_password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of app_password_resets
-- ----------------------------

-- ----------------------------
-- Table structure for app_users
-- ----------------------------
DROP TABLE IF EXISTS `app_users`;
CREATE TABLE `app_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户id',
  `username` varchar(100) NOT NULL DEFAULT '' COMMENT '用户名',
  `phone` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号',
  `email` varchar(100) NOT NULL DEFAULT '' COMMENT '邮箱地址',
  `area` varchar(255) NOT NULL DEFAULT '' COMMENT '地区',
  `password` varchar(64) NOT NULL DEFAULT '' COMMENT '密码',
  `openid` varchar(60) NOT NULL DEFAULT '' COMMENT '微信openid',
  `wx_nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '微信昵称',
  `wx_sex` tinyint(1) NOT NULL DEFAULT '0' COMMENT '微信性别:0=未知,1=男,2=女',
  `wx_city` varchar(50) NOT NULL DEFAULT '' COMMENT '微信城市',
  `wx_province` varchar(50) NOT NULL DEFAULT '0' COMMENT '微信省',
  `wx_country` varchar(50) NOT NULL DEFAULT '' COMMENT '微信国家',
  `wx_headimgurl` varchar(255) NOT NULL DEFAULT '' COMMENT '微信头像url',
  `pin` varchar(64) NOT NULL DEFAULT '' COMMENT '支付密码',
  `pin_error` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'pin密码输错次数，超过三次要重置pin',
  `nickname` varchar(100) NOT NULL DEFAULT '' COMMENT '用户昵称',
  `headimg_url` varchar(255) NOT NULL COMMENT '头像',
  `headimg_thumb` varchar(255) NOT NULL DEFAULT '' COMMENT '头像缩略图',
  `sex` tinyint(1) NOT NULL DEFAULT '3' COMMENT '性别:1=男,2=女,3=未知',
  `signature` varchar(255) NOT NULL DEFAULT '' COMMENT '个性签名',
  `info_category` varchar(255) NOT NULL DEFAULT '' COMMENT '资讯分类id, 多个时用逗号隔开',
  `customer_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '用户类型:1=普通用户,2=商家,3=官方账号',
  `phone_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '手机状态:1=为未认证,2=已认证',
  `email_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '邮箱状态:1=未认证,2=已认证',
  `address` varchar(255) NOT NULL DEFAULT '' COMMENT '详细地址',
  `recommender_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '推荐人id',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态:0=禁用,1=启用',
  `adm_check` tinyint(1) NOT NULL DEFAULT '0' COMMENT '资料信息是否后台验证:0 =否，1= 是',
  `email_pin_error` tinyint(1) NOT NULL DEFAULT '0' COMMENT '修改邮件时,密码输错次数，超过三次禁止登陆',
  `recommend_code` varchar(32) NOT NULL DEFAULT '' COMMENT '个人推荐码',
  `is_reggift` tinyint(1) NOT NULL DEFAULT '0' COMMENT '返现:0=为普通用户，1=为注册赠送礼金用户',
  `is_pc_create` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否在pc端注册: 0= 否,1=是 , 2=超管添加的',
  `language` varchar(20) NOT NULL DEFAULT 'cn' COMMENT '语言',
  `login_error` tinyint(1) NOT NULL DEFAULT '0' COMMENT '登录错误次数',
  `now_os` tinyint(1) NOT NULL DEFAULT '0' COMMENT '当前登录操作系统:0=未知;1=安卓,2=ios',
  `registration_id` varchar(100) NOT NULL DEFAULT '' COMMENT '推送id',
  `fingerprint` varchar(255) NOT NULL DEFAULT '' COMMENT '用户指纹',
  `fingerprint_login_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '指纹支付开关1为关闭，2为开启',
  `fingerprint_pay_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '指纹登录开关:1=关闭，2=开启',
  `face` varchar(100) DEFAULT '' COMMENT '人脸标识',
  `face_pay_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '人脸支付开关:1=关闭，2=开启',
  `face_login_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '人脸登录开关:1=关闭，2=开启',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `api_token` varchar(255) NOT NULL DEFAULT '' COMMENT 'api_token',
  `app_token` varchar(64) NOT NULL DEFAULT '' COMMENT 'app加密使用的token',
  `remember_token` varchar(100) NOT NULL DEFAULT '' COMMENT 'remember_token',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of app_users
-- ----------------------------
INSERT INTO `app_users` VALUES ('3', '126****33@qq.com', '', '1262638533@qq.com', '', '$2y$10$awLjTvkUFQscSJysOy6RjeYC7LzqXFYY66gwo7aaaaeZA6r.KaJEq', '', '', '0', '', '0', '', '', '', '0', '', 'storage/img/defaultlogo.png', '', '3', '', '', '1', '1', '2', '', '0', '1', '0', '0', '7UJZ', '0', '0', 'cn', '0', '1', '1111', '', '1', '1', null, '1', '1', '2019-11-28 09:24:36', '2019-11-29 03:36:38', '', 'S7X0BPXYNOOD2KPCJRD5FZ34KHU6KVTAZP09AV36XDDSXJXOJN50VYKK3F7R9983', '');

-- ----------------------------
-- Table structure for app_users_info
-- ----------------------------
DROP TABLE IF EXISTS `app_users_info`;
CREATE TABLE `app_users_info` (
  `uid` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户id',
  `birthday` int(10) NOT NULL DEFAULT '0' COMMENT '用户生日',
  `id_card` varchar(20) NOT NULL DEFAULT '' COMMENT '身份证号',
  `back_photo` varchar(255) NOT NULL DEFAULT '' COMMENT '身份证背面照',
  `front_photo` varchar(255) NOT NULL DEFAULT '' COMMENT '身份证正面照',
  `hand_photo` varchar(255) NOT NULL DEFAULT '' COMMENT '身份证手持照',
  `address` varchar(255) NOT NULL DEFAULT '' COMMENT '详细地址',
  `recommender_id` bigint(20) NOT NULL DEFAULT '0' COMMENT '推荐人id',
  `business_type_name` varchar(100) DEFAULT '' COMMENT '商家类别名称(独资企业,合伙企业)',
  `business_name` varchar(100) DEFAULT '' COMMENT '商家名称',
  `business_legal_name` varchar(50) DEFAULT NULL COMMENT '法人名称',
  `business_legal_card` varchar(20) DEFAULT '' COMMENT '法人省份证',
  `business_phone` varchar(50) DEFAULT '' COMMENT '商家电话',
  `business_email` varchar(50) DEFAULT '' COMMENT '商家邮箱',
  `business_address` varchar(100) DEFAULT '' COMMENT '商家地址',
  `business_license_img` varchar(255) DEFAULT '' COMMENT '商家营业执照url路径',
  `business_license_code` varchar(255) DEFAULT '' COMMENT '营业执照统一代码',
  `last_lat` decimal(10,7) NOT NULL DEFAULT '0.0000000' COMMENT '最后出现的经度',
  `last_lng` decimal(10,7) NOT NULL DEFAULT '0.0000000' COMMENT '最后出现的纬度',
  `last_ip` varchar(20) NOT NULL DEFAULT '' COMMENT '最后登录ip',
  `last_time` datetime DEFAULT NULL COMMENT '最后登陆时间',
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of app_users_info
-- ----------------------------
INSERT INTO `app_users_info` VALUES ('3', '0', '', '', '', '', '', '0', '', '', null, '', '', '', '', '', '', '0.0000000', '0.0000000', '', null);

-- ----------------------------
-- Table structure for app_user_log_logins
-- ----------------------------
DROP TABLE IF EXISTS `app_user_log_logins`;
CREATE TABLE `app_user_log_logins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL COMMENT '用户ID',
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT '用户名称',
  `ip` char(20) COLLATE utf8_unicode_ci NOT NULL COMMENT '用户IP地址',
  `type` varchar(50) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '事件类型',
  `device` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '?????',
  `login_token` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' COMMENT '登陆token',
  `desc` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT '单设备登录',
  `redistime` varchar(50) COLLATE utf8_unicode_ci NOT NULL COMMENT '存储在redis的时间戳',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=25 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Records of app_user_log_logins
-- ----------------------------
INSERT INTO `app_user_log_logins` VALUES ('1', '3', '126****33@qq.com', '127.0.0.1', '', 'apizza-extension', '3098e55cecc8ef032e507a97fcf4a3c8e70f9b63', 'af45bacbc04975c2a6395ca888d84640', '15749976025EpN', '2019-11-29 03:20:02', '2019-11-29 03:20:02');
INSERT INTO `app_user_log_logins` VALUES ('2', '3', '126****33@qq.com', '127.0.0.1', '', 'apizza-extension', '1fdc570709d4287f9081f2b4ea036daf73e09347', '3d41e3d09124c06c3580f19bc68b2ad6', '1574997834TG8r', '2019-11-29 03:23:54', '2019-11-29 03:23:54');
INSERT INTO `app_user_log_logins` VALUES ('3', '3', '126****33@qq.com', '127.0.0.1', '', 'apizza-extension', 'f20eba892efd45c341a1f946c89416d18519871a', '6ed512d2f7ff0c0c75afbae2926fc479', '1574998044KNAR', '2019-11-29 03:27:24', '2019-11-29 03:27:24');
INSERT INTO `app_user_log_logins` VALUES ('4', '3', '126****33@qq.com', '127.0.0.1', '', 'apizza-extension', '4c5c31d10415015c03cafec17cd790c7113e0851', '249c2462cdcdb0c59bd6573f43e22b37', '15749980980fgc', '2019-11-29 03:28:18', '2019-11-29 03:28:18');
INSERT INTO `app_user_log_logins` VALUES ('5', '3', '126****33@qq.com', '127.0.0.1', '', 'apizza-extension', 'ae27f44ebf8f25b0bc34d4022045f4dfa8c8f598', '477d742b7150d38eb6de8932b927ba2a', '1574998126AT3z', '2019-11-29 03:28:46', '2019-11-29 03:28:46');
INSERT INTO `app_user_log_logins` VALUES ('6', '3', '126****33@qq.com', '127.0.0.1', '', 'apizza-extension', 'e09d25c9f61368ab29a758d7f171283aab59db78', 'f86c964997c370397ce7d45586e9853a', '1574998169DKQn', '2019-11-29 03:29:29', '2019-11-29 03:29:29');
INSERT INTO `app_user_log_logins` VALUES ('7', '3', '126****33@qq.com', '127.0.0.1', '', 'apizza-extension', 'e2ac8553a70f24fb284b65d9edfca349f42f3235', '64f82be5b1050764ee14fccf66ad89a4', '15749984849Zz0', '2019-11-29 03:34:44', '2019-11-29 03:34:44');
INSERT INTO `app_user_log_logins` VALUES ('8', '3', '126****33@qq.com', '127.0.0.1', '', 'apizza-extension', '66ab8cb0d86bc623490091167090845ca4eb7e87', '2f5fa472844f04124d2f42cb5266f877', '1574998529Uboj', '2019-11-29 03:35:29', '2019-11-29 03:35:29');
INSERT INTO `app_user_log_logins` VALUES ('9', '3', '126****33@qq.com', '127.0.0.1', '', 'apizza-extension', '03008091a2035d7893bdd81c104d2ffc349c75f6', 'e085e7838a258b1574570a1ae483de80', '15749985462yfO', '2019-11-29 03:35:46', '2019-11-29 03:35:46');
INSERT INTO `app_user_log_logins` VALUES ('10', '3', '126****33@qq.com', '127.0.0.1', '', 'apizza-extension', 'cbacc8d2fde3ed8f45694caeaecf769df746723c', '5725da6ded90af2e94776dd75b60431f', '1574998598z97v', '2019-11-29 03:36:38', '2019-11-29 03:36:38');
INSERT INTO `app_user_log_logins` VALUES ('11', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', '1986d807b217683a0338cd6e57f24ad131a087ed', '439ca65078afd838edb1630dc5b26856', '1589425212IVzG', '2020-05-14 03:00:12', '2020-05-14 03:00:12');
INSERT INTO `app_user_log_logins` VALUES ('12', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', 'd634553cfb8e5eb2370bd053626ec3493f60842b', '70d147e7b3f86056c162bfc2158bb5aa', '15894252381jrc', '2020-05-14 03:00:38', '2020-05-14 03:00:38');
INSERT INTO `app_user_log_logins` VALUES ('13', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', 'd0494df132d565a53da61b64af02c4ca1a1a095c', '9e8369270c1779d61e578da45c9a8e36', '1589425323lGo7', '2020-05-14 03:02:03', '2020-05-14 03:02:03');
INSERT INTO `app_user_log_logins` VALUES ('14', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', '266df08a752116e2be0f95440b0866cd8c452d8c', '0218b5240ab6cd272086cd9b627de173', '1589425730TT9W', '2020-05-14 03:08:50', '2020-05-14 03:08:50');
INSERT INTO `app_user_log_logins` VALUES ('15', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', 'ca4b688e427b08dd8c814eb846dadd517ea486ce', '70d615ac4a34f4268e58ad0cf568772b', '15894257436Bwj', '2020-05-14 03:09:03', '2020-05-14 03:09:03');
INSERT INTO `app_user_log_logins` VALUES ('16', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', '4fb282a04b878a91790620874064e08bd0f980c6', 'be40f35e6b76f273026cd5779cf0b512', '15894257652dxy', '2020-05-14 03:09:25', '2020-05-14 03:09:25');
INSERT INTO `app_user_log_logins` VALUES ('17', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', '6e0783c46c1b8cd892251f637c4378aed9d9688c', '755e876b1bdfc3dda0cebcf59de66a55', '1589425788MpfB', '2020-05-14 03:09:48', '2020-05-14 03:09:48');
INSERT INTO `app_user_log_logins` VALUES ('18', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', '068c38e5126fa47acb4f35fa400fdf6cc9efb1fd', '93efbd4dc1df52126e14f879ca7d9c75', '15894257895Tas', '2020-05-14 03:09:49', '2020-05-14 03:09:49');
INSERT INTO `app_user_log_logins` VALUES ('19', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', 'face87b6018227cff8137eae0aba7861689ee64f', '60ef9e80b6439468debecd726878730e', '1589425790bRl6', '2020-05-14 03:09:50', '2020-05-14 03:09:50');
INSERT INTO `app_user_log_logins` VALUES ('20', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', '5e1680d5a585189d9fba217787cdedba21bf6181', '9409fbd82c07a409aa1f20960fa47c63', '1589426614pgnS', '2020-05-14 03:23:34', '2020-05-14 03:23:34');
INSERT INTO `app_user_log_logins` VALUES ('21', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', '60989217d7532c0d0bef2ce8d6c10596369b918e', '7bc2756ebbe92f2756ce6a0022f74c5c', '15894266871tJV', '2020-05-14 03:24:47', '2020-05-14 03:24:47');
INSERT INTO `app_user_log_logins` VALUES ('22', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', '12e18d9c2e8eec46e13267aafb2201456633e705', 'a09f5a71fdc8dda7b6d87a00f64b3303', '1589426694YfdT', '2020-05-14 03:24:54', '2020-05-14 03:24:54');
INSERT INTO `app_user_log_logins` VALUES ('23', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', 'ebbe598d7f0dffbcfe245bebfc1459fe0138e18c', 'ca18fa8300a2f1c29fbe63a154f04dfc', '1589426697ZQBD', '2020-05-14 03:24:57', '2020-05-14 03:24:57');
INSERT INTO `app_user_log_logins` VALUES ('24', '3', '126****33@qq.com', '127.0.0.1', '', 'PostmanRuntime/7.24.1', '4aef8e2c51016f3249560be8d661855320f1da42', '58c7c87228239473e008f92e7a3c30f2', '1589426779Hrys', '2020-05-14 03:26:19', '2020-05-14 03:26:19');

-- ----------------------------
-- Table structure for app_user_log_logout
-- ----------------------------
DROP TABLE IF EXISTS `app_user_log_logout`;
CREATE TABLE `app_user_log_logout` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `userId` int(11) NOT NULL COMMENT '用户ID',
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT '用户名称',
  `ip` char(20) COLLATE utf8_unicode_ci NOT NULL COMMENT '用户IP地址',
  `type` varchar(50) COLLATE utf8_unicode_ci NOT NULL COMMENT '事件类型',
  `device` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT '登陆设备号',
  `sigle_token` varchar(255) COLLATE utf8_unicode_ci NOT NULL COMMENT '单设备登录',
  `redistime` varchar(50) COLLATE utf8_unicode_ci NOT NULL COMMENT '存储在redis的时间戳',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `login_token` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Records of app_user_log_logout
-- ----------------------------
