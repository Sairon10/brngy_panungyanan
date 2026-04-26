/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-12.1.2-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: barangay_system
-- ------------------------------------------------------
-- Server version	12.1.2-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `admin_activity`
--

DROP TABLE IF EXISTS `admin_activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `last_activity` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_online` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_admin` (`admin_id`),
  KEY `idx_is_online` (`is_online`),
  KEY `idx_last_activity` (`last_activity`),
  CONSTRAINT `admin_activity_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_activity`
--

LOCK TABLES `admin_activity` WRITE;
/*!40000 ALTER TABLE `admin_activity` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `admin_activity` VALUES
(1,1,'2026-01-07 12:56:36',1);
/*!40000 ALTER TABLE `admin_activity` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `barangay_clearances`
--

DROP TABLE IF EXISTS `barangay_clearances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `barangay_clearances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `clearance_number` varchar(50) NOT NULL,
  `purpose` text NOT NULL,
  `validity_days` int(11) NOT NULL DEFAULT 30,
  `status` enum('pending','approved','rejected','released') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `pdf_generated_at` timestamp NULL DEFAULT NULL,
  `pdf_generated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `clearance_number` (`clearance_number`),
  KEY `user_id` (`user_id`),
  KEY `approved_by` (`approved_by`),
  KEY `pdf_generated_by` (`pdf_generated_by`),
  CONSTRAINT `barangay_clearances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `barangay_clearances_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `barangay_clearances_ibfk_3` FOREIGN KEY (`pdf_generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `barangay_clearances`
--

LOCK TABLES `barangay_clearances` WRITE;
/*!40000 ALTER TABLE `barangay_clearances` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `barangay_clearances` VALUES
(3,5,'BC-2026-000005','Local Employment',30,'released','',1,'2026-01-07 12:15:07','2026-01-07 12:15:13',1,'2026-01-07 09:15:29');
/*!40000 ALTER TABLE `barangay_clearances` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `document_requests`
--

DROP TABLE IF EXISTS `document_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `doc_type` varchar(100) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','released') DEFAULT 'pending',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `indigency_purposes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `document_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_requests`
--

LOCK TABLES `document_requests` WRITE;
/*!40000 ALTER TABLE `document_requests` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `document_requests` VALUES
(2,5,'Indigency','kjkkjjkkj','released','','2026-01-07 08:31:01',NULL),
(3,4,'Indigency','Financial Assistance','released','','2026-01-07 08:50:03',NULL),
(4,5,'Indigency','tatatatabga','released','','2026-01-07 08:51:06',NULL),
(5,4,'Indigency','Financial Assistance','released','','2026-01-07 09:01:37','Financial/Medical Assistance'),
(6,5,'Resident ID','ekqwekq','released','','2026-01-07 10:30:47',NULL),
(7,5,'Indigency','rqwrqwrqwewqkeq kwkeq keqw','released','','2026-01-07 11:20:46',NULL),
(8,5,'Business Talk 2025','ewoqeqoweoqw','released','','2026-01-07 11:23:24',NULL),
(9,5,'Barangay Indigency','elqwelqw','released','','2026-01-07 11:35:19','Financial/Medical Assistance'),
(10,6,'Resident ID','bobo','released','','2026-01-07 13:04:52',NULL);
/*!40000 ALTER TABLE `document_requests` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `document_types`
--

DROP TABLE IF EXISTS `document_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `requires_validity` tinyint(1) NOT NULL DEFAULT 0,
  `requires_special_handling` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `price` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_types`
--

LOCK TABLES `document_types` WRITE;
/*!40000 ALTER TABLE `document_types` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `document_types` VALUES
(1,'Barangay Clearance',1,1,1,1,50.00,'2026-01-07 10:46:17','2026-01-07 10:48:50'),
(2,'Certificate of Residency',0,0,1,2,0.00,'2026-01-07 10:46:17','2026-01-07 10:46:17'),
(3,'Business Talk 2025',0,0,1,3,20.00,'2026-01-07 10:46:17','2026-01-07 11:23:05'),
(4,'Resident ID',0,0,1,4,0.00,'2026-01-07 10:46:17','2026-01-07 10:46:17'),
(9,'Barangay Indigency',0,0,1,0,200.00,'2026-01-07 11:35:00','2026-01-07 11:35:00');
/*!40000 ALTER TABLE `document_types` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `incident_messages`
--

DROP TABLE IF EXISTS `incident_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `incident_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incident_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_incident_id` (`incident_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `incident_messages_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incident_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incident_messages`
--

LOCK TABLES `incident_messages` WRITE;
/*!40000 ALTER TABLE `incident_messages` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `incident_messages` VALUES
(1,6,1,'eqqewq','2026-01-07 07:19:01'),
(2,6,1,'wqeweqqw','2026-01-07 08:32:54'),
(3,7,5,'mqwekqwekqw','2026-01-07 10:21:51'),
(4,7,1,'qweqweqweqweqw qweqweqw','2026-01-07 10:46:26'),
(5,7,5,'rkrkara','2026-01-07 10:50:30'),
(6,7,1,'weqeqweqw','2026-01-07 10:50:50'),
(7,7,1,'t1t21t12t12','2026-01-07 10:50:55'),
(8,7,5,'bobo','2026-01-07 10:51:14'),
(9,8,6,'lwlqwleqwlkeqw','2026-01-07 13:00:02'),
(10,8,6,'tit3','2026-01-07 13:00:06');
/*!40000 ALTER TABLE `incident_messages` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `incidents`
--

DROP TABLE IF EXISTS `incidents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `incidents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('submitted','in_review','resolved') DEFAULT 'submitted',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `admin_response` text DEFAULT NULL,
  `admin_response_by` int(11) DEFAULT NULL,
  `admin_response_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `admin_response_by` (`admin_response_by`),
  CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incidents_ibfk_2` FOREIGN KEY (`admin_response_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidents`
--

LOCK TABLES `incidents` WRITE;
/*!40000 ALTER TABLE `incidents` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `incidents` VALUES
(5,1,'eqwewqewq',14.3831885,120.8820963,NULL,'submitted','2026-01-07 07:02:29',NULL,NULL,NULL),
(6,1,'eqqewq',14.3740870,120.8966446,NULL,'submitted','2026-01-07 07:19:01','wqeweqqw',1,'2026-01-07 08:32:54'),
(7,5,'mqwekqwekqw',14.3784312,120.8881182,'uploads/incidents/incident_1767781311_3e7e6f53d7660d28.jpg','submitted','2026-01-07 10:21:51','t1t21t12t12',1,'2026-01-07 10:50:55'),
(8,6,'lwlqwleqwlkeqw',14.3871207,120.8898616,'uploads/incidents/incident_1767790802_d0ed26b89b5a4d31.png','resolved','2026-01-07 13:00:02',NULL,NULL,NULL);
/*!40000 ALTER TABLE `incidents` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('incident_response','incident_update','general') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `related_incident_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `related_incident_id` (`related_incident_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`related_incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `notifications` VALUES
(6,1,'incident_update','New Incident Reported','A new incident has been reported by a resident.',0,5,'2026-01-07 07:02:29'),
(7,1,'incident_update','New Incident Reported','A new incident has been reported by a resident.',0,6,'2026-01-07 07:19:01'),
(8,1,'incident_response','Admin Response','wqeweqqw',0,6,'2026-01-07 08:32:54'),
(9,5,'general','Barangay Clearance PDF Generated','Your Barangay Clearance (No. BC-2026-000005) PDF has been generated and is ready for pickup at the barangay office.',0,NULL,'2026-01-07 10:12:56'),
(10,1,'incident_update','New Incident Reported','A new incident has been reported by a resident.',0,7,'2026-01-07 10:21:51'),
(11,5,'general','Barangay Clearance PDF Generated','Your Barangay Clearance (No. BC-2026-000005) PDF has been generated and is ready for pickup at the barangay office.',0,NULL,'2026-01-07 10:33:40'),
(12,5,'general','Barangay Clearance PDF Generated','Your Barangay Clearance (No. BC-2026-000005) PDF has been generated and is ready for pickup at the barangay office.',0,NULL,'2026-01-07 10:33:47'),
(13,5,'incident_response','Admin Response','ewqeqewq',0,7,'2026-01-07 10:36:42'),
(14,5,'incident_response','Admin Response','bobo',0,7,'2026-01-07 10:38:27'),
(15,5,'incident_response','Admin Response','bobo',0,7,'2026-01-07 10:43:37'),
(16,5,'incident_response','Admin Response','qweqweqweqweqw qweqweqw',0,7,'2026-01-07 10:46:26'),
(17,1,'incident_update','New Reply on Incident','A resident replied to incident #7.',0,7,'2026-01-07 10:50:30'),
(18,5,'incident_response','Admin Response','weqeqweqw',0,7,'2026-01-07 10:50:50'),
(19,5,'incident_response','Admin Response','t1t21t12t12',0,7,'2026-01-07 10:50:55'),
(20,1,'incident_update','New Reply on Incident','A resident replied to incident #7.',0,7,'2026-01-07 10:51:14'),
(21,5,'general','Barangay Clearance PDF Generated','Your Barangay Clearance (No. BC-2026-000005) PDF has been generated and is ready for pickup at the barangay office.',0,NULL,'2026-01-07 11:37:00'),
(22,5,'general','Barangay Clearance PDF Generated','Your Barangay Clearance (No. BC-2026-000005) PDF has been generated and is ready for pickup at the barangay office.',0,NULL,'2026-01-07 11:54:15'),
(23,5,'general','Barangay Clearance PDF Generated','Your Barangay Clearance (No. BC-2026-000005) PDF has been generated and is ready for pickup at the barangay office.',0,NULL,'2026-01-07 11:58:29'),
(24,5,'general','Barangay Clearance PDF Generated','Your Barangay Clearance (No. BC-2026-000005) PDF has been generated and is ready for pickup at the barangay office.',0,NULL,'2026-01-07 12:01:38'),
(25,5,'general','Barangay Clearance PDF Generated','Your Barangay Clearance (No. BC-2026-000005) PDF has been generated and is ready for pickup at the barangay office.',0,NULL,'2026-01-07 12:15:13'),
(26,1,'incident_update','New Incident Reported','A new incident has been reported by a resident.',0,8,'2026-01-07 13:00:02'),
(27,1,'incident_update','New Reply on Incident','A resident replied to incident #8.',0,8,'2026-01-07 13:00:06');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `password_resets` VALUES
(4,5,'70c5da2ca2d7c02800fa0e3ebfae780c184a712c802a679602c7c4d2822a68b8','2026-01-07 13:42:24',1,'2026-01-07 12:42:24');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `resident_records`
--

DROP TABLE IF EXISTS `resident_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resident_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(190) DEFAULT NULL,
  `full_name` varchar(190) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `sex` enum('Male','Female','Other') DEFAULT NULL,
  `citizenship` varchar(100) DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Divorced','Separated') DEFAULT NULL,
  `household_id` varchar(64) DEFAULT NULL,
  `barangay_id` varchar(64) DEFAULT NULL,
  `purok` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `resident_records_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resident_records`
--

LOCK TABLES `resident_records` WRITE;
/*!40000 ALTER TABLE `resident_records` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `resident_records` VALUES
(3,'crisantosc072@gmail.com','Rollen Jay Asistores',NULL,NULL,NULL,NULL,'033',NULL,'2025-01-08','Male',NULL,NULL,NULL,NULL,NULL,1,1,'2026-01-07 08:01:54','2026-01-07 08:04:43'),
(4,NULL,'Kevin Luistro Olegario','Kevin','Olegario','Luistro',NULL,'Et eos exercitatione','+1 (115) 308-8643','2013-08-05','Male','Quo sed a voluptate','Widowed',NULL,NULL,'Enim optio impedit',1,1,'2026-01-07 12:48:51','2026-01-07 12:50:39');
/*!40000 ALTER TABLE `resident_records` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `residents`
--

DROP TABLE IF EXISTS `residents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `residents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `sex` enum('Male','Female','Other') DEFAULT NULL,
  `household_id` varchar(64) DEFAULT NULL,
  `barangay_id` varchar(64) DEFAULT NULL,
  `purok` varchar(100) DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `id_document_path` varchar(255) DEFAULT NULL,
  `address_on_id` text DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `citizenship` varchar(100) DEFAULT NULL,
  `civil_status` enum('Single','Married','Widowed','Divorced','Separated') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `residents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `residents_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `residents`
--

LOCK TABLES `residents` WRITE;
/*!40000 ALTER TABLE `residents` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `residents` VALUES
(3,4,'033',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'verified',NULL,NULL,NULL,'2026-01-07 08:18:31',1,NULL,NULL),
(4,5,'033','+63 9566943713','uploads/profile_pictures/profile_5_1767783365_2a60009e.jpg','2002-01-07','Male','','','2','verified','id_5_1767773461.jpg',NULL,NULL,'2026-01-07 08:18:29',1,'Filipino','Single'),
(5,6,'Et eos exercitatione','+639918108012','uploads/profile_pictures/profile_6_1767790489_1923689c.jpg','2004-01-12','Male','','','Purok 6','verified','id_6_1767790721.png','kewqkeqwkeqw',NULL,'2026-01-07 12:59:10',1,'Filipino','Single');
/*!40000 ALTER TABLE `residents` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `support_chats`
--

DROP TABLE IF EXISTS `support_chats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('open','waiting','active','closed') DEFAULT 'open',
  `assigned_admin_id` int(11) DEFAULT NULL,
  `last_message_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `guest_name` varchar(255) DEFAULT NULL,
  `guest_contact` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_assigned_admin` (`assigned_admin_id`),
  CONSTRAINT `support_chats_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_chats_ibfk_2` FOREIGN KEY (`assigned_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_chats`
--

LOCK TABLES `support_chats` WRITE;
/*!40000 ALTER TABLE `support_chats` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `support_chats` VALUES
(1,5,'active',1,'2026-01-07 12:09:45','2026-01-07 12:08:47',NULL,NULL,NULL),
(2,NULL,'closed',NULL,'2026-01-07 12:24:33','2026-01-07 12:23:13','2026-01-07 12:56:39','Altair','asistoresl@gmail.com');
/*!40000 ALTER TABLE `support_chats` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `support_messages`
--

DROP TABLE IF EXISTS `support_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `sender_type` enum('user','admin') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `idx_chat_id` (`chat_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_is_read` (`is_read`),
  CONSTRAINT `support_messages_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `support_chats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_messages`
--

LOCK TABLES `support_messages` WRITE;
/*!40000 ALTER TABLE `support_messages` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `support_messages` VALUES
(1,1,5,'admin','Hello! You\'ve been connected to a support agent. How can we help you today?',1,'2026-01-07 12:08:47'),
(2,1,1,'admin','bobo',1,'2026-01-07 12:08:53'),
(3,1,1,'admin','tanginamo',1,'2026-01-07 12:08:56'),
(4,1,1,'admin','dati kabang tanga',1,'2026-01-07 12:08:58'),
(5,1,5,'user','oo',1,'2026-01-07 12:09:00'),
(6,1,5,'user','tatatanga',1,'2026-01-07 12:09:34'),
(7,1,5,'user','dati ka bang tanga',1,'2026-01-07 12:09:40'),
(8,1,1,'admin','bobobobob',1,'2026-01-07 12:09:44'),
(9,1,1,'admin','bobbobo',1,'2026-01-07 12:09:45'),
(11,2,1,'admin','bano',1,'2026-01-07 12:23:22'),
(14,2,1,'admin','rar',1,'2026-01-07 12:23:32'),
(15,2,1,'admin','rasras',1,'2026-01-07 12:23:33'),
(18,2,1,'admin','tt',1,'2026-01-07 12:24:29'),
(19,2,1,'admin','eqweqw',1,'2026-01-07 12:24:33');
/*!40000 ALTER TABLE `support_messages` ENABLE KEYS */;
UNLOCK TABLES;
commit;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(190) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(190) NOT NULL,
  `role` enum('resident','admin') NOT NULL DEFAULT 'resident',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `users` VALUES
(1,'admin@panungyanan.local','$2y$12$DGMaKGzi5beDp4wa3HeKY.7wLpgAUZtOYbVasu10RMB8BB6/F9NHS','System Administrator','admin','2025-09-28 09:42:36',NULL,NULL,NULL,NULL),
(4,'crisantosc072112@gmail.com','$2y$12$CPpqZws17uw1kJ/Y5t0Jc.jsiSRAuCOKgKf6X3AqY4dD9ltxLC5ka','Rollen Jay Asistores','resident','2026-01-07 08:02:05',NULL,NULL,NULL,NULL),
(5,'crisantosc072@gmail.com','$2y$12$9xfyPwoqWZr7ldb5oo.IGO4uCx3ELwg9DpM4rNTX9Xjk8z./s1NMO','Rollen Jay Asistores','resident','2026-01-07 08:04:58','Rollen','Asistores','Jay',NULL),
(6,NULL,'$2y$12$Sxu6TXKRT9S/IBmnWowqGuGHEWfyMzC0UGf71w9ek466/D5NnTqky','Kevin Luistro Olegario','resident','2026-01-07 12:51:37','Kevin','Olegario','Luistro',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
commit;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-01-07 21:14:34
