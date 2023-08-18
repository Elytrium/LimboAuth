
# TOTP and Other

###### /system/MFA

[click files](https://cdn.quickfirecorp.ru/?dir=files/system/ "click files")


# SQL

###### core_members dump

```
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `forum`
--

-- --------------------------------------------------------

--
-- Структура таблицы `core_members`
--

CREATE TABLE `core_members` (
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `member_group_id` smallint(6) NOT NULL DEFAULT 0,
  `email` varchar(150) NOT NULL DEFAULT '',
  `joined` int(11) NOT NULL DEFAULT 0,
  `ip_address` varchar(46) NOT NULL DEFAULT '',
  `skin` smallint(6) DEFAULT NULL,
  `warn_level` int(11) DEFAULT NULL,
  `warn_lastwarn` int(11) NOT NULL DEFAULT 0,
  `language` mediumint(9) DEFAULT NULL,
  `restrict_post` int(11) NOT NULL DEFAULT 0,
  `bday_day` int(11) DEFAULT NULL,
  `bday_month` int(11) DEFAULT NULL,
  `bday_year` int(11) DEFAULT NULL,
  `msg_count_new` int(11) NOT NULL DEFAULT 0,
  `msg_count_total` int(11) NOT NULL DEFAULT 0,
  `msg_count_reset` int(11) NOT NULL DEFAULT 0,
  `msg_show_notification` int(11) NOT NULL DEFAULT 0,
  `last_visit` int(11) DEFAULT 0,
  `last_activity` int(11) DEFAULT 0,
  `mod_posts` int(11) NOT NULL DEFAULT 0,
  `auto_track` varchar(256) DEFAULT '0',
  `temp_ban` int(11) DEFAULT 0,
  `mgroup_others` varchar(245) NOT NULL DEFAULT '',
  `members_seo_name` varchar(255) NOT NULL DEFAULT '',
  `members_cache` mediumtext DEFAULT NULL,
  `failed_logins` text DEFAULT NULL,
  `failed_login_count` smallint(6) NOT NULL DEFAULT 0,
  `members_profile_views` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `members_pass_hash` varchar(255) DEFAULT NULL,
  `members_pass_salt` varchar(22) DEFAULT NULL,
  `members_bitoptions` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `members_day_posts` varchar(32) NOT NULL DEFAULT '0,0',
  `notification_cnt` mediumint(9) NOT NULL DEFAULT 0,
  `pp_last_visitors` text DEFAULT NULL,
  `pp_main_photo` text DEFAULT NULL,
  `pp_main_width` int(11) DEFAULT NULL,
  `pp_main_height` int(11) DEFAULT NULL,
  `pp_thumb_photo` text DEFAULT NULL,
  `pp_thumb_width` int(11) DEFAULT NULL,
  `pp_thumb_height` int(11) DEFAULT NULL,
  `pp_setting_count_comments` int(11) DEFAULT 0,
  `pp_reputation_points` int(11) DEFAULT NULL,
  `pp_photo_type` varchar(20) DEFAULT NULL,
  `signature` text DEFAULT NULL,
  `pconversation_filters` text DEFAULT NULL,
  `pp_customization` mediumtext DEFAULT NULL,
  `timezone` varchar(64) DEFAULT NULL,
  `pp_cover_photo` varchar(255) NOT NULL DEFAULT '',
  `profilesync` text DEFAULT NULL,
  `profilesync_lastsync` int(11) NOT NULL DEFAULT 0 COMMENT 'Indicates the last time any profile sync service was ran',
  `allow_admin_mails` bit(1) DEFAULT b'0',
  `members_bitoptions2` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `create_menu` text DEFAULT NULL COMMENT 'Cached contents of the "Create" drop down menu.',
  `members_disable_pm` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 - not disabled, 1 - disabled, member can re-enable, 2 - disabled',
  `marked_site_read` int(10) UNSIGNED DEFAULT 0,
  `pp_cover_offset` int(11) NOT NULL DEFAULT 0,
  `acp_language` mediumint(9) DEFAULT NULL,
  `member_title` varchar(64) DEFAULT NULL,
  `member_posts` mediumint(9) NOT NULL DEFAULT 0,
  `member_last_post` int(11) DEFAULT NULL,
  `member_streams` text DEFAULT NULL,
  `photo_last_update` int(11) DEFAULT NULL,
  `mfa_details` text DEFAULT NULL,
  `failed_mfa_attempts` smallint(5) UNSIGNED DEFAULT 0 COMMENT 'Number of times tried and failed MFA',
  `permission_array` text DEFAULT NULL COMMENT 'A cache of the clubs and social groups that the member is in',
  `completed` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Whether the account is completed or not',
  `achievements_points` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The number of achievement points the member has',
  `unique_hash` varchar(255) DEFAULT NULL,
  `latest_alert` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Indicates the last alert that was viewed',
  `idm_block_submissions` tinyint(3) UNSIGNED DEFAULT 0 COMMENT 'Blocked from submitting Downloads files?',
  `conv_password` varchar(255) DEFAULT NULL,
  `conv_password_extra` varchar(255) DEFAULT NULL,
  `cm_credits` text DEFAULT NULL,
  `cm_no_sev` tinyint(4) DEFAULT 0,
  `cm_return_group` smallint(6) DEFAULT 0,
  `ISSUEDTIME` bigint(20) DEFAULT NULL,
  `MINECRAFT_REGISTER_DATE` bigint(20) DEFAULT NULL,
  `LOGINDATE` bigint(20) DEFAULT NULL,
  `UUID` varchar(255) DEFAULT NULL,
  `PREMIUMUUID` varchar(255) DEFAULT NULL,
  `login_ip_address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `core_members`
--

INSERT INTO `core_members` (`member_id`, `name`, `member_group_id`, `email`, `joined`, `ip_address`, `skin`, `warn_level`, `warn_lastwarn`, `language`, `restrict_post`, `bday_day`, `bday_month`, `bday_year`, `msg_count_new`, `msg_count_total`, `msg_count_reset`, `msg_show_notification`, `last_visit`, `last_activity`, `mod_posts`, `auto_track`, `temp_ban`, `mgroup_others`, `members_seo_name`, `members_cache`, `failed_logins`, `failed_login_count`, `members_profile_views`, `members_pass_hash`, `members_pass_salt`, `members_bitoptions`, `members_day_posts`, `notification_cnt`, `pp_last_visitors`, `pp_main_photo`, `pp_main_width`, `pp_main_height`, `pp_thumb_photo`, `pp_thumb_width`, `pp_thumb_height`, `pp_setting_count_comments`, `pp_reputation_points`, `pp_photo_type`, `signature`, `pconversation_filters`, `pp_customization`, `timezone`, `pp_cover_photo`, `profilesync`, `profilesync_lastsync`, `allow_admin_mails`, `members_bitoptions2`, `create_menu`, `members_disable_pm`, `marked_site_read`, `pp_cover_offset`, `acp_language`, `member_title`, `member_posts`, `member_last_post`, `member_streams`, `photo_last_update`, `mfa_details`, `failed_mfa_attempts`, `permission_array`, `completed`, `achievements_points`, `unique_hash`, `latest_alert`, `idm_block_submissions`, `conv_password`, `conv_password_extra`, `cm_credits`, `cm_no_sev`, `cm_return_group`, `ISSUEDTIME`, `MINECRAFT_REGISTER_DATE`, `LOGINDATE`, `UUID`, `PREMIUMUUID`, `login_ip_address`) VALUES
(3, 'test', 3, 'test@mail.org', 1691117005, '95.140.153.130', NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, 0, 0, 0, 0, 1691135277, 1691138166, 0, '{\"content\":0,\"comments\":0,\"method\":\"immediate\"}', 0, '', 'nukilopsaepet', NULL, '[]', 0, 18, '$2y$10$YJKngTcfN0bf1DkbTMs4o.adz97SCx1JCKvQX6Og/fMtVccgiYObi', NULL, 1073807360, '0,0', 0, NULL, 'c49962', NULL, NULL, NULL, NULL, NULL, 0, 0, 'none', '', NULL, NULL, 'UTC', '', '[]', 1691138166, b'1', 16777216, '{\"menu_key\":\"762153382\",\"menu\":{\"gallery_image\":{\"link\":\"https:\\/\\/quickfirecorp.ru\\/gallery\\/submit\\/?_new=1\",\"extraData\":{\"data-ipsDialog-size\":\"medium\",\"data-ipsDialog\":\"true\",\"data-ipsDialog-destructOnClose\":\"true\",\"data-ipsDialog-close\":\"false\",\"data-ipsDialog-extraClass\":\"cGalleryDialog_outer\",\"data-ipsDialog-remoteSubmit\":\"true\"}},\"event\":{\"link\":\"https:\\/\\/quickfirecorp.ru\\/events\\/submit\\/?do=submit&id=1\"},\"file_download\":{\"link\":\"https:\\/\\/quickfirecorp.ru\\/files\\/submit\\/?do=submit&_new=1&category=1\"},\"news_entry\":{\"link\":\"https:\\/\\/quickfirecorp.ru\\/news\\/add\\/\",\"title\":\"news_select_category\",\"extraData\":{\"data-ipsDialog\":true,\"data-ipsDialog-size\":\"narrow\"}}}}', 0, 0, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0, '', b'1', 0, NULL, 0, 0, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `core_members`
--
ALTER TABLE `core_members`
  ADD PRIMARY KEY (`member_id`),
  ADD KEY `bday_day` (`bday_day`),
  ADD KEY `bday_month` (`bday_month`),
  ADD KEY `members_bitoptions` (`members_bitoptions`),
  ADD KEY `ip_address` (`ip_address`),
  ADD KEY `failed_login_count` (`failed_login_count`),
  ADD KEY `joined` (`joined`),
  ADD KEY `email` (`email`),
  ADD KEY `member_groups` (`member_group_id`,`mgroup_others`(188)),
  ADD KEY `mgroup` (`member_id`,`member_group_id`),
  ADD KEY `allow_admin_mails` (`allow_admin_mails`),
  ADD KEY `name_index` (`name`(191)),
  ADD KEY `mod_posts` (`mod_posts`),
  ADD KEY `photo_last_update` (`photo_last_update`),
  ADD KEY `last_activity` (`last_activity`),
  ADD KEY `completed` (`completed`,`temp_ban`),
  ADD KEY `profilesync` (`profilesync_lastsync`,`profilesync`(181)),
  ADD KEY `member_posts` (`member_posts`,`member_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `core_members`
--
ALTER TABLE `core_members`
  MODIFY `member_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
```
