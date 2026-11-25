/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `control_gauge_task_adrs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `control_gauge_task_adrs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `adr` char(4) NOT NULL,
  `at` datetime NOT NULL,
  `who` varchar(80) NOT NULL DEFAULT '',
  `removed_at` datetime DEFAULT NULL,
  `removed_by` varchar(80) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `task_adr` (`task_id`,`adr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `control_gauge_task_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `control_gauge_task_steps` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` int(10) unsigned NOT NULL,
  `pos` int(11) NOT NULL DEFAULT 0,
  `see` varchar(600) NOT NULL DEFAULT '',
  `do_what` varchar(600) NOT NULL DEFAULT '',
  `confirm` varchar(600) NOT NULL DEFAULT '',
  `at` datetime NOT NULL,
  `who` varchar(80) NOT NULL DEFAULT '',
  `done_at` datetime DEFAULT NULL,
  `done_by` varchar(80) NOT NULL DEFAULT '',
  `tz` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `task_pos` (`task_id`,`pos`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `control_gauge_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `control_gauge_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `at` datetime NOT NULL,
  `tz` varchar(64) NOT NULL DEFAULT '',
  `gauge` varchar(80) NOT NULL,
  `item` varchar(255) NOT NULL DEFAULT '',
  `kind` varchar(10) NOT NULL,
  `what` varchar(400) NOT NULL DEFAULT '',
  `bucket` varchar(10) NOT NULL DEFAULT '',
  `value` varchar(255) NOT NULL DEFAULT '',
  `who` varchar(80) NOT NULL DEFAULT '',
  `uid` int(11) NOT NULL DEFAULT 0,
  `undone_at` datetime DEFAULT NULL,
  `cleared_at` datetime DEFAULT NULL,
  `why` varchar(400) NOT NULL DEFAULT '',
  `source` varchar(255) NOT NULL DEFAULT '',
  `owner` varchar(80) NOT NULL DEFAULT '',
  `owner_at` datetime DEFAULT NULL,
  `rel` varchar(120) NOT NULL DEFAULT '',
  `done_how` varchar(255) NOT NULL DEFAULT '',
  `goal` text DEFAULT NULL,
  `in_scope` text DEFAULT NULL,
  `out_scope` text DEFAULT NULL,
  `done_when` text DEFAULT NULL,
  `priority` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gauge_item` (`gauge`,`item`),
  KEY `kind_at` (`kind`,`at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `control_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `control_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `at` datetime NOT NULL,
  `tz` varchar(64) NOT NULL DEFAULT '',
  `kind` varchar(20) NOT NULL,
  `proc` varchar(60) NOT NULL DEFAULT '',
  `what` varchar(400) NOT NULL,
  `who` varchar(80) NOT NULL DEFAULT 'the system',
  `why` varchar(400) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `at` (`at`),
  KEY `proc_at` (`proc`,`at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

