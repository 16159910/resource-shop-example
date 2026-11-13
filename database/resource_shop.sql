-- Resource Shop: schema and synthetic sample data only.
-- Import into a new empty MySQL 5.7+ database using utf8mb4.
SET NAMES utf8mb4;
SET SQL_MODE='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

CREATE TABLE `shop_admin` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(128) NOT NULL COMMENT '密码',
  `nickname` varchar(50) DEFAULT '' COMMENT '昵称',
  `avatar` varchar(255) DEFAULT '' COMMENT '头像',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1启用 0禁用',
  `last_login_time` int(11) DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` varchar(50) DEFAULT '' COMMENT '最后登录IP',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员';

CREATE TABLE `shop_card_key` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `material_id` int(11) NOT NULL COMMENT '所属卡密商品',
  `card_no` varchar(255) NOT NULL COMMENT '卡号 / 激活码',
  `card_secret` varchar(255) NOT NULL DEFAULT '' COMMENT '卡密 / 密码，单码卡留空',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0未售 1已售',
  `order_id` int(11) NOT NULL DEFAULT '0',
  `user_id` int(11) NOT NULL DEFAULT '0',
  `sold_time` int(11) NOT NULL DEFAULT '0',
  `batch_no` varchar(32) NOT NULL DEFAULT '' COMMENT '导入批次',
  `create_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_material_card` (`material_id`,`card_no`(191)),
  KEY `idx_material_status` (`material_id`,`status`),
  KEY `idx_order` (`order_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='卡密仓库';

CREATE TABLE `shop_cart` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `create_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_material` (`user_id`,`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `shop_category` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL DEFAULT '0' COMMENT '父级ID',
  `name` varchar(100) NOT NULL COMMENT '分类名称',
  `icon` varchar(100) DEFAULT '' COMMENT '图标',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资料分类';

CREATE TABLE `shop_download_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1购买下载 2资料下载',
  `ip` varchar(50) DEFAULT '',
  `create_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_material` (`user_id`,`material_id`),
  KEY `idx_user_type_time` (`user_id`,`type`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='下载记录';

CREATE TABLE `shop_favorite` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `create_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_material` (`user_id`,`material_id`),
  KEY `idx_material` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资料收藏';

CREATE TABLE `shop_freebie` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL COMMENT '标题',
  `cover` varchar(255) NOT NULL DEFAULT '' COMMENT '封面',
  `summary` varchar(500) NOT NULL DEFAULT '' COMMENT '简介',
  `claim_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '领取方式 1直接 2公众号 3购买指定资料 4订单满N单',
  `need_material_ids` varchar(500) NOT NULL DEFAULT '' COMMENT '方式3：需购买的资料ID，逗号分隔，任一即可',
  `need_order_count` int(11) NOT NULL DEFAULT '0' COMMENT '方式4：需要的已支付订单数',
  `mp_name` varchar(100) NOT NULL DEFAULT '' COMMENT '方式2：公众号名称',
  `mp_qr` varchar(255) NOT NULL DEFAULT '' COMMENT '方式2：公众号二维码',
  `mp_keyword` varchar(50) NOT NULL DEFAULT '' COMMENT '方式2：回复关键词',
  `claim_tip` varchar(255) NOT NULL DEFAULT '' COMMENT '补充说明，显示在领取页',
  `pan_url` varchar(500) NOT NULL DEFAULT '' COMMENT '网盘链接',
  `pan_code` varchar(100) NOT NULL DEFAULT '' COMMENT '网盘提取码',
  `file_path` varchar(500) NOT NULL DEFAULT '' COMMENT '文件路径',
  `file_name` varchar(200) NOT NULL DEFAULT '' COMMENT '文件名',
  `file_size` bigint(20) NOT NULL DEFAULT '0',
  `claim_count` int(11) NOT NULL DEFAULT '0' COMMENT '已领取人数',
  `sort` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1上架 0下架',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_sort` (`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='免费资料';

CREATE TABLE `shop_freebie_claim` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `freebie_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `create_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_freebie_user` (`freebie_id`,`user_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='免费资料领取记录';

CREATE TABLE `shop_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL DEFAULT '0',
  `module` varchar(50) DEFAULT '' COMMENT '模块',
  `action` varchar(50) DEFAULT '' COMMENT '操作类型',
  `description` varchar(500) DEFAULT '' COMMENT '描述',
  `ip` varchar(50) DEFAULT '',
  `create_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志';

CREATE TABLE `shop_material` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL DEFAULT '0' COMMENT '分类ID',
  `goods_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '商品类型 1资料 2卡密',
  `title` varchar(200) NOT NULL COMMENT '标题',
  `cover` varchar(255) DEFAULT '' COMMENT '封面图',
  `summary` varchar(500) DEFAULT '' COMMENT '简介摘要',
  `description` longtext COMMENT '详情介绍(富文本)',
  `catalog` text COMMENT '目录(JSON)',
  `page_count` int(11) NOT NULL DEFAULT '0' COMMENT '页数，0 表示未填',
  `file_format` varchar(20) NOT NULL DEFAULT '' COMMENT '文件格式：PDF / Word / PPT / Excel 等',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '价格',
  `original_price` decimal(10,2) DEFAULT '0.00' COMMENT '划线价',
  `sales` int(11) NOT NULL DEFAULT '0' COMMENT '销量',
  `views` int(11) NOT NULL DEFAULT '0' COMMENT '浏览量',
  `delivery_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '发货方式 1=网盘链接 2=直接下载',
  `pan_url` varchar(500) DEFAULT '' COMMENT '网盘链接',
  `pan_code` varchar(100) DEFAULT '' COMMENT '网盘提取码',
  `file_path` varchar(500) DEFAULT '' COMMENT '直接下载文件路径',
  `file_name` varchar(200) DEFAULT '' COMMENT '原始文件名',
  `file_size` bigint(20) DEFAULT '0' COMMENT '文件大小(字节)',
  `use_url` varchar(500) NOT NULL DEFAULT '' COMMENT '卡密商品：使用地址',
  `use_guide` text COMMENT '卡密商品：使用说明',
  `wechat` varchar(100) DEFAULT '' COMMENT '客服微信',
  `tags` varchar(500) DEFAULT '' COMMENT '标签(逗号分隔)',
  `seo_title` varchar(200) DEFAULT '' COMMENT 'SEO标题',
  `seo_keywords` varchar(300) DEFAULT '' COMMENT 'SEO关键词',
  `seo_description` varchar(500) DEFAULT '' COMMENT 'SEO描述',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态 1=上架 0=下架 2=草稿',
  `is_hidden` tinyint(1) NOT NULL DEFAULT '0' COMMENT '隐藏 0否 1是（列表不展示，直链可访问）',
  `is_hot` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否热门',
  `is_recommend` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否推荐',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_status_sort` (`status`,`sort`,`id`),
  KEY `idx_status_hidden` (`status`,`is_hidden`),
  FULLTEXT KEY `ft_material_search` (`title`,`tags`,`summary`) /*!50100 WITH PARSER `ngram` */ 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资料';

CREATE TABLE `shop_material_preview` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `material_id` int(11) NOT NULL COMMENT '资料ID',
  `image_path` varchar(255) NOT NULL COMMENT '图片路径(已加水印)',
  `original_path` varchar(255) DEFAULT '' COMMENT '原始图片路径',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `create_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_material` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资料试看页';

CREATE TABLE `shop_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) NOT NULL COMMENT '订单号',
  `user_id` int(11) NOT NULL COMMENT '用户ID',
  `material_id` int(11) NOT NULL DEFAULT '0',
  `title` varchar(200) NOT NULL COMMENT '资料标题快照',
  `cover` varchar(255) DEFAULT '' COMMENT '封面快照',
  `amount` decimal(10,2) NOT NULL COMMENT '支付金额',
  `pay_type` tinyint(1) DEFAULT NULL COMMENT '支付方式 1=支付宝 2=微信',
  `pay_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '支付状态 0=待支付 1=已支付 2=已退款',
  `pay_time` int(11) DEFAULT NULL COMMENT '支付时间',
  `trade_no` varchar(100) DEFAULT '' COMMENT '第三方交易号',
  `delivery_type` tinyint(1) DEFAULT NULL COMMENT '发货方式',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '订单状态 0=待支付 1=已完成 2=已退款',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  `platform` tinyint(1) NOT NULL DEFAULT '0' COMMENT '网站业务字段',
  `pay_client` varchar(12) NOT NULL DEFAULT '' COMMENT '网站业务字段',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_no` (`order_no`),
  KEY `idx_user` (`user_id`),
  KEY `idx_material` (`material_id`),
  KEY `idx_platform_create` (`platform`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单';

CREATE TABLE `shop_order_item` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL COMMENT '订单ID',
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '订单号',
  `material_id` int(11) NOT NULL COMMENT '资料ID',
  `title` varchar(200) DEFAULT '' COMMENT '资料标题快照',
  `cover` varchar(255) DEFAULT '' COMMENT '封面快照',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '成交价快照',
  `delivery_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1网盘 2直接下载',
  `create_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_order_no` (`order_no`),
  KEY `idx_material` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单明细';

CREATE TABLE `shop_payment_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint(1) NOT NULL COMMENT '1=支付宝 2=微信支付',
  `name` varchar(50) NOT NULL,
  `config` longtext COMMENT 'JSON配置',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付配置';

CREATE TABLE `shop_question` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT '提问用户',
  `content` text COMMENT '问题内容',
  `contact` varchar(100) DEFAULT '' COMMENT '联系方式，选填',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0待回复 1已回复',
  `reply` text COMMENT '后台回复',
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '回复人',
  `reply_time` int(11) DEFAULT NULL,
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户提问';

CREATE TABLE `shop_system_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL COMMENT '配置键',
  `value` text COMMENT '配置值',
  `type` varchar(20) NOT NULL DEFAULT 'string' COMMENT '类型',
  `group` varchar(50) NOT NULL DEFAULT 'base' COMMENT '分组',
  `name` varchar(100) DEFAULT '' COMMENT '配置名称',
  `remark` varchar(300) DEFAULT '' COMMENT '备注',
  `sort` int(11) NOT NULL DEFAULT '0',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置';

CREATE TABLE `shop_user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nickname` varchar(100) DEFAULT '' COMMENT '昵称',
  `avatar` varchar(255) DEFAULT '' COMMENT '头像',
  `phone` varchar(20) DEFAULT NULL COMMENT '手机号',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `password` varchar(128) DEFAULT '' COMMENT '密码',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `register_ip` varchar(50) DEFAULT '',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_phone` (`phone`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='前台用户';

START TRANSACTION;
INSERT INTO `shop_admin` (`id`,`username`,`password`,`nickname`,`avatar`,`status`,`create_time`,`update_time`) VALUES (1,'dongqing','$2y$12$n/iby1DuvzHkLOs83N4vheDw5S.W1Wd..ng1F0XL.QGU8bmiQ.tW2','示例管理员','',1,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('site_name','资料小铺','string','base','site_name','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('site_logo','','string','base','site_logo','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('site_favicon','','string','base','site_favicon','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('site_keywords','数字资料,示例模板,资料商店','string','base','site_keywords','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('site_description','为工作、生活与兴趣收集一些实用的小方法。本站所有内容均为虚构示例。','string','base','site_description','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('site_icp','','string','base','site_icp','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('site_footer','© 2026 资料小铺 · 示例内容','string','base','site_footer','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('default_wechat','','string','base','default_wechat','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('default_wechat_qr','','string','base','default_wechat_qr','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('watermark_text','资料小铺 · 示例预览','string','base','watermark_text','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('watermark_opacity','35','string','base','watermark_opacity','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('smtp_host','','string','base','smtp_host','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('smtp_port','465','string','base','smtp_port','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('smtp_user','','string','base','smtp_user','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('smtp_pass','','string','base','smtp_pass','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('smtp_from_name','资料小铺','string','base','smtp_from_name','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('wecom_webhook','','string','base','wecom_webhook','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('wecom_notify_pending','0','string','base','wecom_notify_pending','',0,1794557858,1794557858);
INSERT INTO `shop_system_config` (`key`,`value`,`type`,`group`,`name`,`remark`,`sort`,`create_time`,`update_time`) VALUES ('wecom_notify_paid','0','string','base','wecom_notify_paid','',0,1794557858,1794557858);
INSERT INTO `shop_category` (`id`,`parent_id`,`name`,`icon`,`sort`,`status`,`create_time`,`update_time`) VALUES (1,0,'工作与效率','fa-folder',1,1,1794557858,1794557858);
INSERT INTO `shop_category` (`id`,`parent_id`,`name`,`icon`,`sort`,`status`,`create_time`,`update_time`) VALUES (2,0,'生活与整理','fa-folder',2,1,1794557858,1794557858);
INSERT INTO `shop_category` (`id`,`parent_id`,`name`,`icon`,`sort`,`status`,`create_time`,`update_time`) VALUES (3,0,'学习与记录','fa-folder',3,1,1794557858,1794557858);
INSERT INTO `shop_category` (`id`,`parent_id`,`name`,`icon`,`sort`,`status`,`create_time`,`update_time`) VALUES (4,0,'活动与灵感','fa-folder',4,1,1794557858,1794557858);
INSERT INTO `shop_material` (`id`,`category_id`,`goods_type`,`title`,`cover`,`summary`,`description`,`catalog`,`page_count`,`file_format`,`price`,`original_price`,`sales`,`views`,`delivery_type`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`use_url`,`use_guide`,`wechat`,`tags`,`seo_title`,`seo_keywords`,`seo_description`,`status`,`is_hidden`,`is_hot`,`is_recommend`,`sort`,`create_time`,`update_time`) VALUES (1,1,1,'专注时间记录表','/uploads/samples/cover-01.svg','用四个小章节，把专注时间记录表的使用思路整理清楚。附一份可编辑的示例记录。','<p>一份关于专注时间记录表的虚构样本，用于展示资料结构与使用场景。</p><h3>包含哪些内容</h3><p>使用说明、场景示例、操作步骤与完成记录，共四个章节，另附一份 CSV 样例表。</p><h3>一个小场景</h3><p>小林和小周准备一场分享活动。先明确目标，再列出任务、分配角色，最后记录进展并整理反馈。</p><h3>文件说明</h3><p>下载包里的文字与记录均为重新制作的虚构示例，可用来体验浏览、下单与下载。</p>','[{"level": 1, "label": "01", "title": "使用说明", "page": 1}, {"level": 1, "label": "02", "title": "场景示例", "page": 2}, {"level": 1, "label": "03", "title": "操作步骤", "page": 3}, {"level": 1, "label": "04", "title": "完成记录", "page": 4}]',0,'ZIP',6.9,0,0,0,2,'','','protected/samples/material-01.zip','专注时间记录表-示例.zip',1603,'','','','工作与效率,示例,专注','专注时间记录表 - 资料小铺','工作与效率,示例','用四个小章节，把专注时间记录表的使用思路整理清楚。附一份可编辑的示例记录。',1,0,1,1,99,1794518258,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (1,'/uploads/samples/preview-01-1.svg','',1,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (1,'/uploads/samples/preview-01-2.svg','',2,1794557858);
INSERT INTO `shop_material` (`id`,`category_id`,`goods_type`,`title`,`cover`,`summary`,`description`,`catalog`,`page_count`,`file_format`,`price`,`original_price`,`sales`,`views`,`delivery_type`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`use_url`,`use_guide`,`wechat`,`tags`,`seo_title`,`seo_keywords`,`seo_description`,`status`,`is_hidden`,`is_hot`,`is_recommend`,`sort`,`create_time`,`update_time`) VALUES (2,1,1,'每周行动计划','/uploads/samples/cover-02.svg','用四个小章节，把每周行动计划的使用思路整理清楚。附一份可编辑的示例记录。','<p>一份关于每周行动计划的虚构样本，用于展示资料结构与使用场景。</p><h3>包含哪些内容</h3><p>使用说明、场景示例、操作步骤与完成记录，共四个章节，另附一份 CSV 样例表。</p><h3>一个小场景</h3><p>小林和小周准备一场分享活动。先明确目标，再列出任务、分配角色，最后记录进展并整理反馈。</p><h3>文件说明</h3><p>下载包里的文字与记录均为重新制作的虚构示例，可用来体验浏览、下单与下载。</p>','[{"level": 1, "label": "01", "title": "使用说明", "page": 1}, {"level": 1, "label": "02", "title": "场景示例", "page": 2}, {"level": 1, "label": "03", "title": "操作步骤", "page": 3}, {"level": 1, "label": "04", "title": "完成记录", "page": 4}]',0,'ZIP',9.9,0,0,0,2,'','','protected/samples/material-02.zip','每周行动计划-示例.zip',1587,'','','','工作与效率,示例,每周','每周行动计划 - 资料小铺','工作与效率,示例','用四个小章节，把每周行动计划的使用思路整理清楚。附一份可编辑的示例记录。',1,0,1,1,98,1794521858,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (2,'/uploads/samples/preview-02-1.svg','',1,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (2,'/uploads/samples/preview-02-2.svg','',2,1794557858);
INSERT INTO `shop_material` (`id`,`category_id`,`goods_type`,`title`,`cover`,`summary`,`description`,`catalog`,`page_count`,`file_format`,`price`,`original_price`,`sales`,`views`,`delivery_type`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`use_url`,`use_guide`,`wechat`,`tags`,`seo_title`,`seo_keywords`,`seo_description`,`status`,`is_hidden`,`is_hot`,`is_recommend`,`sort`,`create_time`,`update_time`) VALUES (3,1,1,'轻量项目看板','/uploads/samples/cover-03.svg','用四个小章节，把轻量项目看板的使用思路整理清楚。附一份可编辑的示例记录。','<p>一份关于轻量项目看板的虚构样本，用于展示资料结构与使用场景。</p><h3>包含哪些内容</h3><p>使用说明、场景示例、操作步骤与完成记录，共四个章节，另附一份 CSV 样例表。</p><h3>一个小场景</h3><p>小林和小周准备一场分享活动。先明确目标，再列出任务、分配角色，最后记录进展并整理反馈。</p><h3>文件说明</h3><p>下载包里的文字与记录均为重新制作的虚构示例，可用来体验浏览、下单与下载。</p>','[{"level": 1, "label": "01", "title": "使用说明", "page": 1}, {"level": 1, "label": "02", "title": "场景示例", "page": 2}, {"level": 1, "label": "03", "title": "操作步骤", "page": 3}, {"level": 1, "label": "04", "title": "完成记录", "page": 4}]',0,'ZIP',0,0,0,0,2,'','','protected/samples/material-03.zip','轻量项目看板-示例.zip',1594,'','','','工作与效率,示例,轻量','轻量项目看板 - 资料小铺','工作与效率,示例','用四个小章节，把轻量项目看板的使用思路整理清楚。附一份可编辑的示例记录。',1,0,1,1,97,1794525458,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (3,'/uploads/samples/preview-03-1.svg','',1,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (3,'/uploads/samples/preview-03-2.svg','',2,1794557858);
INSERT INTO `shop_material` (`id`,`category_id`,`goods_type`,`title`,`cover`,`summary`,`description`,`catalog`,`page_count`,`file_format`,`price`,`original_price`,`sales`,`views`,`delivery_type`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`use_url`,`use_guide`,`wechat`,`tags`,`seo_title`,`seo_keywords`,`seo_description`,`status`,`is_hidden`,`is_hot`,`is_recommend`,`sort`,`create_time`,`update_time`) VALUES (4,2,1,'旅行准备清单','/uploads/samples/cover-04.svg','用四个小章节，把旅行准备清单的使用思路整理清楚。附一份可编辑的示例记录。','<p>一份关于旅行准备清单的虚构样本，用于展示资料结构与使用场景。</p><h3>包含哪些内容</h3><p>使用说明、场景示例、操作步骤与完成记录，共四个章节，另附一份 CSV 样例表。</p><h3>一个小场景</h3><p>小林和小周准备一场分享活动。先明确目标，再列出任务、分配角色，最后记录进展并整理反馈。</p><h3>文件说明</h3><p>下载包里的文字与记录均为重新制作的虚构示例，可用来体验浏览、下单与下载。</p>','[{"level": 1, "label": "01", "title": "使用说明", "page": 1}, {"level": 1, "label": "02", "title": "场景示例", "page": 2}, {"level": 1, "label": "03", "title": "操作步骤", "page": 3}, {"level": 1, "label": "04", "title": "完成记录", "page": 4}]',0,'ZIP',6.9,0,0,0,2,'','','protected/samples/material-04.zip','旅行准备清单-示例.zip',1582,'','','','生活与整理,示例,旅行','旅行准备清单 - 资料小铺','生活与整理,示例','用四个小章节，把旅行准备清单的使用思路整理清楚。附一份可编辑的示例记录。',1,0,1,1,96,1794529058,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (4,'/uploads/samples/preview-04-1.svg','',1,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (4,'/uploads/samples/preview-04-2.svg','',2,1794557858);
INSERT INTO `shop_material` (`id`,`category_id`,`goods_type`,`title`,`cover`,`summary`,`description`,`catalog`,`page_count`,`file_format`,`price`,`original_price`,`sales`,`views`,`delivery_type`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`use_url`,`use_guide`,`wechat`,`tags`,`seo_title`,`seo_keywords`,`seo_description`,`status`,`is_hidden`,`is_hot`,`is_recommend`,`sort`,`create_time`,`update_time`) VALUES (5,2,1,'日常预算记录','/uploads/samples/cover-05.svg','用四个小章节，把日常预算记录的使用思路整理清楚。附一份可编辑的示例记录。','<p>一份关于日常预算记录的虚构样本，用于展示资料结构与使用场景。</p><h3>包含哪些内容</h3><p>使用说明、场景示例、操作步骤与完成记录，共四个章节，另附一份 CSV 样例表。</p><h3>一个小场景</h3><p>小林和小周准备一场分享活动。先明确目标，再列出任务、分配角色，最后记录进展并整理反馈。</p><h3>文件说明</h3><p>下载包里的文字与记录均为重新制作的虚构示例，可用来体验浏览、下单与下载。</p>','[{"level": 1, "label": "01", "title": "使用说明", "page": 1}, {"level": 1, "label": "02", "title": "场景示例", "page": 2}, {"level": 1, "label": "03", "title": "操作步骤", "page": 3}, {"level": 1, "label": "04", "title": "完成记录", "page": 4}]',0,'ZIP',9.9,0,0,0,2,'','','protected/samples/material-05.zip','日常预算记录-示例.zip',1592,'','','','生活与整理,示例,日常','日常预算记录 - 资料小铺','生活与整理,示例','用四个小章节，把日常预算记录的使用思路整理清楚。附一份可编辑的示例记录。',1,0,1,1,95,1794532658,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (5,'/uploads/samples/preview-05-1.svg','',1,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (5,'/uploads/samples/preview-05-2.svg','',2,1794557858);
INSERT INTO `shop_material` (`id`,`category_id`,`goods_type`,`title`,`cover`,`summary`,`description`,`catalog`,`page_count`,`file_format`,`price`,`original_price`,`sales`,`views`,`delivery_type`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`use_url`,`use_guide`,`wechat`,`tags`,`seo_title`,`seo_keywords`,`seo_description`,`status`,`is_hidden`,`is_hot`,`is_recommend`,`sort`,`create_time`,`update_time`) VALUES (6,2,1,'居家整理指南','/uploads/samples/cover-06.svg','用四个小章节，把居家整理指南的使用思路整理清楚。附一份可编辑的示例记录。','<p>一份关于居家整理指南的虚构样本，用于展示资料结构与使用场景。</p><h3>包含哪些内容</h3><p>使用说明、场景示例、操作步骤与完成记录，共四个章节，另附一份 CSV 样例表。</p><h3>一个小场景</h3><p>小林和小周准备一场分享活动。先明确目标，再列出任务、分配角色，最后记录进展并整理反馈。</p><h3>文件说明</h3><p>下载包里的文字与记录均为重新制作的虚构示例，可用来体验浏览、下单与下载。</p>','[{"level": 1, "label": "01", "title": "使用说明", "page": 1}, {"level": 1, "label": "02", "title": "场景示例", "page": 2}, {"level": 1, "label": "03", "title": "操作步骤", "page": 3}, {"level": 1, "label": "04", "title": "完成记录", "page": 4}]',0,'ZIP',12.9,0,0,0,2,'','','protected/samples/material-06.zip','居家整理指南-示例.zip',1580,'','','','生活与整理,示例,居家','居家整理指南 - 资料小铺','生活与整理,示例','用四个小章节，把居家整理指南的使用思路整理清楚。附一份可编辑的示例记录。',1,0,1,1,94,1794536258,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (6,'/uploads/samples/preview-06-1.svg','',1,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (6,'/uploads/samples/preview-06-2.svg','',2,1794557858);
INSERT INTO `shop_material` (`id`,`category_id`,`goods_type`,`title`,`cover`,`summary`,`description`,`catalog`,`page_count`,`file_format`,`price`,`original_price`,`sales`,`views`,`delivery_type`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`use_url`,`use_guide`,`wechat`,`tags`,`seo_title`,`seo_keywords`,`seo_description`,`status`,`is_hidden`,`is_hot`,`is_recommend`,`sort`,`create_time`,`update_time`) VALUES (7,3,1,'阅读笔记模板','/uploads/samples/cover-07.svg','用四个小章节，把阅读笔记模板的使用思路整理清楚。附一份可编辑的示例记录。','<p>一份关于阅读笔记模板的虚构样本，用于展示资料结构与使用场景。</p><h3>包含哪些内容</h3><p>使用说明、场景示例、操作步骤与完成记录，共四个章节，另附一份 CSV 样例表。</p><h3>一个小场景</h3><p>小林和小周准备一场分享活动。先明确目标，再列出任务、分配角色，最后记录进展并整理反馈。</p><h3>文件说明</h3><p>下载包里的文字与记录均为重新制作的虚构示例，可用来体验浏览、下单与下载。</p>','[{"level": 1, "label": "01", "title": "使用说明", "page": 1}, {"level": 1, "label": "02", "title": "场景示例", "page": 2}, {"level": 1, "label": "03", "title": "操作步骤", "page": 3}, {"level": 1, "label": "04", "title": "完成记录", "page": 4}]',0,'ZIP',6.9,0,0,0,2,'','','protected/samples/material-07.zip','阅读笔记模板-示例.zip',1593,'','','','学习与记录,示例,阅读','阅读笔记模板 - 资料小铺','学习与记录,示例','用四个小章节，把阅读笔记模板的使用思路整理清楚。附一份可编辑的示例记录。',1,0,1,1,93,1794539858,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (7,'/uploads/samples/preview-07-1.svg','',1,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (7,'/uploads/samples/preview-07-2.svg','',2,1794557858);
INSERT INTO `shop_material` (`id`,`category_id`,`goods_type`,`title`,`cover`,`summary`,`description`,`catalog`,`page_count`,`file_format`,`price`,`original_price`,`sales`,`views`,`delivery_type`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`use_url`,`use_guide`,`wechat`,`tags`,`seo_title`,`seo_keywords`,`seo_description`,`status`,`is_hidden`,`is_hot`,`is_recommend`,`sort`,`create_time`,`update_time`) VALUES (8,3,1,'摄影观察笔记','/uploads/samples/cover-08.svg','用四个小章节，把摄影观察笔记的使用思路整理清楚。附一份可编辑的示例记录。','<p>一份关于摄影观察笔记的虚构样本，用于展示资料结构与使用场景。</p><h3>包含哪些内容</h3><p>使用说明、场景示例、操作步骤与完成记录，共四个章节，另附一份 CSV 样例表。</p><h3>一个小场景</h3><p>小林和小周准备一场分享活动。先明确目标，再列出任务、分配角色，最后记录进展并整理反馈。</p><h3>文件说明</h3><p>下载包里的文字与记录均为重新制作的虚构示例，可用来体验浏览、下单与下载。</p>','[{"level": 1, "label": "01", "title": "使用说明", "page": 1}, {"level": 1, "label": "02", "title": "场景示例", "page": 2}, {"level": 1, "label": "03", "title": "操作步骤", "page": 3}, {"level": 1, "label": "04", "title": "完成记录", "page": 4}]',0,'ZIP',9.9,0,0,0,2,'','','protected/samples/material-08.zip','摄影观察笔记-示例.zip',1595,'','','','学习与记录,示例,摄影','摄影观察笔记 - 资料小铺','学习与记录,示例','用四个小章节，把摄影观察笔记的使用思路整理清楚。附一份可编辑的示例记录。',1,0,1,1,92,1794543458,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (8,'/uploads/samples/preview-08-1.svg','',1,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (8,'/uploads/samples/preview-08-2.svg','',2,1794557858);
INSERT INTO `shop_material` (`id`,`category_id`,`goods_type`,`title`,`cover`,`summary`,`description`,`catalog`,`page_count`,`file_format`,`price`,`original_price`,`sales`,`views`,`delivery_type`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`use_url`,`use_guide`,`wechat`,`tags`,`seo_title`,`seo_keywords`,`seo_description`,`status`,`is_hidden`,`is_hot`,`is_recommend`,`sort`,`create_time`,`update_time`) VALUES (9,3,1,'个人目标回顾','/uploads/samples/cover-09.svg','用四个小章节，把个人目标回顾的使用思路整理清楚。附一份可编辑的示例记录。','<p>一份关于个人目标回顾的虚构样本，用于展示资料结构与使用场景。</p><h3>包含哪些内容</h3><p>使用说明、场景示例、操作步骤与完成记录，共四个章节，另附一份 CSV 样例表。</p><h3>一个小场景</h3><p>小林和小周准备一场分享活动。先明确目标，再列出任务、分配角色，最后记录进展并整理反馈。</p><h3>文件说明</h3><p>下载包里的文字与记录均为重新制作的虚构示例，可用来体验浏览、下单与下载。</p>','[{"level": 1, "label": "01", "title": "使用说明", "page": 1}, {"level": 1, "label": "02", "title": "场景示例", "page": 2}, {"level": 1, "label": "03", "title": "操作步骤", "page": 3}, {"level": 1, "label": "04", "title": "完成记录", "page": 4}]',0,'ZIP',0,0,0,0,2,'','','protected/samples/material-09.zip','个人目标回顾-示例.zip',1590,'','','','学习与记录,示例,个人','个人目标回顾 - 资料小铺','学习与记录,示例','用四个小章节，把个人目标回顾的使用思路整理清楚。附一份可编辑的示例记录。',1,0,1,1,91,1794547058,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (9,'/uploads/samples/preview-09-1.svg','',1,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (9,'/uploads/samples/preview-09-2.svg','',2,1794557858);
INSERT INTO `shop_material` (`id`,`category_id`,`goods_type`,`title`,`cover`,`summary`,`description`,`catalog`,`page_count`,`file_format`,`price`,`original_price`,`sales`,`views`,`delivery_type`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`use_url`,`use_guide`,`wechat`,`tags`,`seo_title`,`seo_keywords`,`seo_description`,`status`,`is_hidden`,`is_hot`,`is_recommend`,`sort`,`create_time`,`update_time`) VALUES (10,4,1,'周末活动策划','/uploads/samples/cover-10.svg','用四个小章节，把周末活动策划的使用思路整理清楚。附一份可编辑的示例记录。','<p>一份关于周末活动策划的虚构样本，用于展示资料结构与使用场景。</p><h3>包含哪些内容</h3><p>使用说明、场景示例、操作步骤与完成记录，共四个章节，另附一份 CSV 样例表。</p><h3>一个小场景</h3><p>小林和小周准备一场分享活动。先明确目标，再列出任务、分配角色，最后记录进展并整理反馈。</p><h3>文件说明</h3><p>下载包里的文字与记录均为重新制作的虚构示例，可用来体验浏览、下单与下载。</p>','[{"level": 1, "label": "01", "title": "使用说明", "page": 1}, {"level": 1, "label": "02", "title": "场景示例", "page": 2}, {"level": 1, "label": "03", "title": "操作步骤", "page": 3}, {"level": 1, "label": "04", "title": "完成记录", "page": 4}]',0,'ZIP',6.9,0,0,0,2,'','','protected/samples/material-10.zip','周末活动策划-示例.zip',1577,'','','','活动与灵感,示例,周末','周末活动策划 - 资料小铺','活动与灵感,示例','用四个小章节，把周末活动策划的使用思路整理清楚。附一份可编辑的示例记录。',1,0,1,1,90,1794550658,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (10,'/uploads/samples/preview-10-1.svg','',1,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (10,'/uploads/samples/preview-10-2.svg','',2,1794557858);
INSERT INTO `shop_material` (`id`,`category_id`,`goods_type`,`title`,`cover`,`summary`,`description`,`catalog`,`page_count`,`file_format`,`price`,`original_price`,`sales`,`views`,`delivery_type`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`use_url`,`use_guide`,`wechat`,`tags`,`seo_title`,`seo_keywords`,`seo_description`,`status`,`is_hidden`,`is_hot`,`is_recommend`,`sort`,`create_time`,`update_time`) VALUES (11,4,1,'灵感采集手册','/uploads/samples/cover-11.svg','用四个小章节，把灵感采集手册的使用思路整理清楚。附一份可编辑的示例记录。','<p>一份关于灵感采集手册的虚构样本，用于展示资料结构与使用场景。</p><h3>包含哪些内容</h3><p>使用说明、场景示例、操作步骤与完成记录，共四个章节，另附一份 CSV 样例表。</p><h3>一个小场景</h3><p>小林和小周准备一场分享活动。先明确目标，再列出任务、分配角色，最后记录进展并整理反馈。</p><h3>文件说明</h3><p>下载包里的文字与记录均为重新制作的虚构示例，可用来体验浏览、下单与下载。</p>','[{"level": 1, "label": "01", "title": "使用说明", "page": 1}, {"level": 1, "label": "02", "title": "场景示例", "page": 2}, {"level": 1, "label": "03", "title": "操作步骤", "page": 3}, {"level": 1, "label": "04", "title": "完成记录", "page": 4}]',0,'ZIP',9.9,0,0,0,2,'','','protected/samples/material-11.zip','灵感采集手册-示例.zip',1592,'','','','活动与灵感,示例,灵感','灵感采集手册 - 资料小铺','活动与灵感,示例','用四个小章节，把灵感采集手册的使用思路整理清楚。附一份可编辑的示例记录。',1,0,1,1,89,1794554258,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (11,'/uploads/samples/preview-11-1.svg','',1,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (11,'/uploads/samples/preview-11-2.svg','',2,1794557858);
INSERT INTO `shop_material` (`id`,`category_id`,`goods_type`,`title`,`cover`,`summary`,`description`,`catalog`,`page_count`,`file_format`,`price`,`original_price`,`sales`,`views`,`delivery_type`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`use_url`,`use_guide`,`wechat`,`tags`,`seo_title`,`seo_keywords`,`seo_description`,`status`,`is_hidden`,`is_hot`,`is_recommend`,`sort`,`create_time`,`update_time`) VALUES (12,4,1,'读书会流程表','/uploads/samples/cover-12.svg','用四个小章节，把读书会流程表的使用思路整理清楚。附一份可编辑的示例记录。','<p>一份关于读书会流程表的虚构样本，用于展示资料结构与使用场景。</p><h3>包含哪些内容</h3><p>使用说明、场景示例、操作步骤与完成记录，共四个章节，另附一份 CSV 样例表。</p><h3>一个小场景</h3><p>小林和小周准备一场分享活动。先明确目标，再列出任务、分配角色，最后记录进展并整理反馈。</p><h3>文件说明</h3><p>下载包里的文字与记录均为重新制作的虚构示例，可用来体验浏览、下单与下载。</p>','[{"level": 1, "label": "01", "title": "使用说明", "page": 1}, {"level": 1, "label": "02", "title": "场景示例", "page": 2}, {"level": 1, "label": "03", "title": "操作步骤", "page": 3}, {"level": 1, "label": "04", "title": "完成记录", "page": 4}]',0,'ZIP',12.9,0,0,0,2,'','','protected/samples/material-12.zip','读书会流程表-示例.zip',1580,'','','','活动与灵感,示例,读书','读书会流程表 - 资料小铺','活动与灵感,示例','用四个小章节，把读书会流程表的使用思路整理清楚。附一份可编辑的示例记录。',1,0,1,1,88,1794557858,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (12,'/uploads/samples/preview-12-1.svg','',1,1794557858);
INSERT INTO `shop_material_preview` (`material_id`,`image_path`,`original_path`,`sort`,`create_time`) VALUES (12,'/uploads/samples/preview-12-2.svg','',2,1794557858);
INSERT INTO `shop_user` (`id`,`nickname`,`avatar`,`phone`,`email`,`password`,`status`,`register_ip`,`create_time`,`update_time`) VALUES (1,'示例用户01','',NULL,'demo@example.com','$2y$12$ytnduYWw3M7ZMkS.VZ/U9uN/Gw1.vIESpOKRWNoAhHicZ.ARL3hRu',1,'127.0.0.1',1794471458,1794557858);
INSERT INTO `shop_user` (`id`,`nickname`,`avatar`,`phone`,`email`,`password`,`status`,`register_ip`,`create_time`,`update_time`) VALUES (2,'示例用户02','',NULL,'reader02@example.com','$2y$12$ytnduYWw3M7ZMkS.VZ/U9uN/Gw1.vIESpOKRWNoAhHicZ.ARL3hRu',1,'127.0.0.1',1794385058,1794557858);
INSERT INTO `shop_user` (`id`,`nickname`,`avatar`,`phone`,`email`,`password`,`status`,`register_ip`,`create_time`,`update_time`) VALUES (3,'示例用户03','',NULL,'reader03@example.com','$2y$12$ytnduYWw3M7ZMkS.VZ/U9uN/Gw1.vIESpOKRWNoAhHicZ.ARL3hRu',1,'127.0.0.1',1794298658,1794557858);
INSERT INTO `shop_user` (`id`,`nickname`,`avatar`,`phone`,`email`,`password`,`status`,`register_ip`,`create_time`,`update_time`) VALUES (4,'示例用户04','',NULL,'reader04@example.com','$2y$12$ytnduYWw3M7ZMkS.VZ/U9uN/Gw1.vIESpOKRWNoAhHicZ.ARL3hRu',1,'127.0.0.1',1794212258,1794557858);
INSERT INTO `shop_user` (`id`,`nickname`,`avatar`,`phone`,`email`,`password`,`status`,`register_ip`,`create_time`,`update_time`) VALUES (5,'示例用户05','',NULL,'reader05@example.com','$2y$12$ytnduYWw3M7ZMkS.VZ/U9uN/Gw1.vIESpOKRWNoAhHicZ.ARL3hRu',1,'127.0.0.1',1794125858,1794557858);
INSERT INTO `shop_user` (`id`,`nickname`,`avatar`,`phone`,`email`,`password`,`status`,`register_ip`,`create_time`,`update_time`) VALUES (6,'示例用户06','',NULL,'reader06@example.com','$2y$12$ytnduYWw3M7ZMkS.VZ/U9uN/Gw1.vIESpOKRWNoAhHicZ.ARL3hRu',1,'127.0.0.1',1794039458,1794557858);
INSERT INTO `shop_order` (`id`,`order_no`,`user_id`,`material_id`,`title`,`cover`,`amount`,`pay_type`,`pay_status`,`pay_time`,`trade_no`,`delivery_type`,`status`,`platform`,`pay_client`,`create_time`,`update_time`) VALUES (1,'SAMPLE2026000001',1,1,'专注时间记录表','/uploads/samples/cover-01.svg',6.9,1,1,1794557858,'SAMPLE-ONLY-1',2,1,1,'web',1794557738,1794557858);
INSERT INTO `shop_order_item` (`order_id`,`order_no`,`material_id`,`title`,`cover`,`price`,`delivery_type`,`create_time`) VALUES (1,'SAMPLE2026000001',1,'专注时间记录表','/uploads/samples/cover-01.svg',6.9,2,1794557738);
INSERT INTO `shop_order` (`id`,`order_no`,`user_id`,`material_id`,`title`,`cover`,`amount`,`pay_type`,`pay_status`,`pay_time`,`trade_no`,`delivery_type`,`status`,`platform`,`pay_client`,`create_time`,`update_time`) VALUES (2,'SAMPLE2026000002',1,2,'每周行动计划','/uploads/samples/cover-02.svg',9.9,1,1,1794471458,'SAMPLE-ONLY-2',2,1,1,'web',1794471338,1794471458);
INSERT INTO `shop_order_item` (`order_id`,`order_no`,`material_id`,`title`,`cover`,`price`,`delivery_type`,`create_time`) VALUES (2,'SAMPLE2026000002',2,'每周行动计划','/uploads/samples/cover-02.svg',9.9,2,1794471338);
INSERT INTO `shop_order` (`id`,`order_no`,`user_id`,`material_id`,`title`,`cover`,`amount`,`pay_type`,`pay_status`,`pay_time`,`trade_no`,`delivery_type`,`status`,`platform`,`pay_client`,`create_time`,`update_time`) VALUES (3,'SAMPLE2026000003',5,3,'轻量项目看板','/uploads/samples/cover-03.svg',0,1,1,1794385058,'SAMPLE-ONLY-3',2,1,1,'web',1794384938,1794385058);
INSERT INTO `shop_order_item` (`order_id`,`order_no`,`material_id`,`title`,`cover`,`price`,`delivery_type`,`create_time`) VALUES (3,'SAMPLE2026000003',3,'轻量项目看板','/uploads/samples/cover-03.svg',0,2,1794384938);
INSERT INTO `shop_order` (`id`,`order_no`,`user_id`,`material_id`,`title`,`cover`,`amount`,`pay_type`,`pay_status`,`pay_time`,`trade_no`,`delivery_type`,`status`,`platform`,`pay_client`,`create_time`,`update_time`) VALUES (4,'SAMPLE2026000004',6,4,'旅行准备清单','/uploads/samples/cover-04.svg',6.9,1,1,1794298658,'SAMPLE-ONLY-4',2,1,1,'web',1794298538,1794298658);
INSERT INTO `shop_order_item` (`order_id`,`order_no`,`material_id`,`title`,`cover`,`price`,`delivery_type`,`create_time`) VALUES (4,'SAMPLE2026000004',4,'旅行准备清单','/uploads/samples/cover-04.svg',6.9,2,1794298538);
INSERT INTO `shop_order` (`id`,`order_no`,`user_id`,`material_id`,`title`,`cover`,`amount`,`pay_type`,`pay_status`,`pay_time`,`trade_no`,`delivery_type`,`status`,`platform`,`pay_client`,`create_time`,`update_time`) VALUES (5,'SAMPLE2026000005',2,5,'日常预算记录','/uploads/samples/cover-05.svg',9.9,1,1,1794212258,'SAMPLE-ONLY-5',2,1,1,'web',1794212138,1794212258);
INSERT INTO `shop_order_item` (`order_id`,`order_no`,`material_id`,`title`,`cover`,`price`,`delivery_type`,`create_time`) VALUES (5,'SAMPLE2026000005',5,'日常预算记录','/uploads/samples/cover-05.svg',9.9,2,1794212138);
INSERT INTO `shop_order` (`id`,`order_no`,`user_id`,`material_id`,`title`,`cover`,`amount`,`pay_type`,`pay_status`,`pay_time`,`trade_no`,`delivery_type`,`status`,`platform`,`pay_client`,`create_time`,`update_time`) VALUES (6,'SAMPLE2026000006',3,6,'居家整理指南','/uploads/samples/cover-06.svg',12.9,1,1,1794125858,'SAMPLE-ONLY-6',2,1,1,'web',1794125738,1794125858);
INSERT INTO `shop_order_item` (`order_id`,`order_no`,`material_id`,`title`,`cover`,`price`,`delivery_type`,`create_time`) VALUES (6,'SAMPLE2026000006',6,'居家整理指南','/uploads/samples/cover-06.svg',12.9,2,1794125738);
INSERT INTO `shop_order` (`id`,`order_no`,`user_id`,`material_id`,`title`,`cover`,`amount`,`pay_type`,`pay_status`,`pay_time`,`trade_no`,`delivery_type`,`status`,`platform`,`pay_client`,`create_time`,`update_time`) VALUES (7,'SAMPLE2026000007',4,7,'阅读笔记模板','/uploads/samples/cover-07.svg',6.9,1,1,1794039458,'SAMPLE-ONLY-7',2,1,1,'web',1794039338,1794039458);
INSERT INTO `shop_order_item` (`order_id`,`order_no`,`material_id`,`title`,`cover`,`price`,`delivery_type`,`create_time`) VALUES (7,'SAMPLE2026000007',7,'阅读笔记模板','/uploads/samples/cover-07.svg',6.9,2,1794039338);
INSERT INTO `shop_order` (`id`,`order_no`,`user_id`,`material_id`,`title`,`cover`,`amount`,`pay_type`,`pay_status`,`pay_time`,`trade_no`,`delivery_type`,`status`,`platform`,`pay_client`,`create_time`,`update_time`) VALUES (8,'SAMPLE2026000008',5,8,'摄影观察笔记','/uploads/samples/cover-08.svg',9.9,1,1,1793953058,'SAMPLE-ONLY-8',2,1,1,'web',1793952938,1793953058);
INSERT INTO `shop_order_item` (`order_id`,`order_no`,`material_id`,`title`,`cover`,`price`,`delivery_type`,`create_time`) VALUES (8,'SAMPLE2026000008',8,'摄影观察笔记','/uploads/samples/cover-08.svg',9.9,2,1793952938);
INSERT INTO `shop_freebie` (`id`,`title`,`cover`,`summary`,`claim_type`,`claim_tip`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`claim_count`,`sort`,`status`,`create_time`,`update_time`) VALUES (1,'每日待办示例表','/uploads/samples/free-01.svg','可以免费领取的虚构样本，先体验一下内容与下载流程。',1,'','','','protected/samples/free-01.zip','每日待办示例表.zip',1589,0,1,1,1794557858,1794557858);
INSERT INTO `shop_freebie` (`id`,`title`,`cover`,`summary`,`claim_type`,`claim_tip`,`pan_url`,`pan_code`,`file_path`,`file_name`,`file_size`,`claim_count`,`sort`,`status`,`create_time`,`update_time`) VALUES (2,'月度回顾示例卡','/uploads/samples/free-02.svg','可以免费领取的虚构样本，先体验一下内容与下载流程。',1,'','','','protected/samples/free-02.zip','月度回顾示例卡.zip',1589,0,2,1,1794557858,1794557858);
INSERT INTO `shop_cart` (`user_id`,`material_id`,`create_time`) VALUES (1,12,1794557858);
INSERT INTO `shop_question` (`user_id`,`content`,`contact`,`status`,`reply`,`admin_id`,`create_time`,`update_time`) VALUES (2,'示例文件可以在哪里重新下载？','reader02@example.com',0,'',0,1794557858,1794557858);
INSERT INTO `shop_payment_config` (`type`,`name`,`config`,`status`,`create_time`,`update_time`) VALUES (1,'支付宝','{}',0,1794557858,1794557858);
INSERT INTO `shop_payment_config` (`type`,`name`,`config`,`status`,`create_time`,`update_time`) VALUES (2,'微信支付','{}',0,1794557858,1794557858);
UPDATE `shop_material` m SET sales=(SELECT COUNT(*) FROM `shop_order_item` i JOIN `shop_order` o ON o.id=i.order_id WHERE i.material_id=m.id AND o.pay_status=1);
COMMIT;
