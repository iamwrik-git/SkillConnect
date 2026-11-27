-- MySQL dump 10.13  Distrib 8.0.44, for macos12.7 (arm64)
--
-- Host: 127.0.0.1    Database: skillconnect
-- ------------------------------------------------------
-- Server version	8.0.44

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `mentorships`
--

DROP TABLE IF EXISTS `mentorships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mentorships` (
  `request_id` int NOT NULL AUTO_INCREMENT,
  `trainer_id` int NOT NULL,
  `trainee_id` int NOT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `idx_mentorships_status` (`status`),
  KEY `idx_mentorships_trainer_status` (`trainer_id`,`status`),
  KEY `idx_mentorships_trainee_status` (`trainee_id`,`status`),
  CONSTRAINT `mentorships_ibfk_1` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `mentorships_ibfk_2` FOREIGN KEY (`trainee_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mentorships`
--

LOCK TABLES `mentorships` WRITE;
/*!40000 ALTER TABLE `mentorships` DISABLE KEYS */;
INSERT INTO `mentorships` VALUES (18,28,26,'pending','2026-11-27 06:49:17'),(19,31,26,'accepted','2026-11-27 06:50:08');
/*!40000 ALTER TABLE `mentorships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `notification_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `recipient_user_id` int NOT NULL,
  `sender_user_id` int DEFAULT NULL,
  `request_id` int DEFAULT NULL,
  `notification_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notifications_recipient_read_created` (`recipient_user_id`,`is_read`,`created_at`),
  KEY `idx_notifications_request_id` (`request_id`),
  KEY `idx_notifications_sender` (`sender_user_id`),
  KEY `idx_notifications_conversation` (`notification_type`,`sender_user_id`,`recipient_user_id`,`created_at`),
  KEY `idx_notifications_type_recipient` (`notification_type`,`recipient_user_id`,`is_read`),
  CONSTRAINT `fk_notifications_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_request` FOREIGN KEY (`request_id`) REFERENCES `mentorships` (`request_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (22,28,NULL,18,'mentorship_request','Wrik requested your mentorship.',0,'2026-11-27 06:49:17'),(23,31,NULL,19,'mentorship_request','Wrik requested your mentorship.',1,'2026-11-27 06:50:08'),(24,26,NULL,19,'mentorship_accepted','Kunal Kushwaha accepted your mentorship request.',1,'2026-11-27 06:51:14');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saved_trainers`
--

DROP TABLE IF EXISTS `saved_trainers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `saved_trainers` (
  `save_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `trainer_id` int NOT NULL,
  `saved_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`save_id`),
  UNIQUE KEY `user_id` (`user_id`,`trainer_id`),
  KEY `trainer_id` (`trainer_id`),
  CONSTRAINT `saved_trainers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `saved_trainers_ibfk_2` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saved_trainers`
--

LOCK TABLES `saved_trainers` WRITE;
/*!40000 ALTER TABLE `saved_trainers` DISABLE KEYS */;
INSERT INTO `saved_trainers` VALUES (18,26,28,'2026-11-27 06:47:02');
/*!40000 ALTER TABLE `saved_trainers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `skills`
--

DROP TABLE IF EXISTS `skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `skills` (
  `skill_id` int NOT NULL AUTO_INCREMENT,
  `skill_name` varchar(50) NOT NULL,
  PRIMARY KEY (`skill_id`),
  UNIQUE KEY `skill_name` (`skill_name`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `skills`
--

LOCK TABLES `skills` WRITE;
/*!40000 ALTER TABLE `skills` DISABLE KEYS */;
INSERT INTO `skills` VALUES (53,'AI Engineering'),(38,'Amazon Web Services'),(23,'Backend Development'),(60,'Blockchain'),(1,'C'),(9,'C#'),(2,'C++'),(43,'CI/CD'),(37,'Cloud Computing'),(13,'Computer Architecture'),(15,'Computer Networks'),(49,'Computer Vision'),(57,'Cryptography'),(29,'CSS'),(54,'Cybersecurity'),(44,'Data Analysis'),(45,'Data Science'),(11,'Data Structures & Algorithms'),(16,'Database Management'),(47,'Deep Learning'),(64,'Devops'),(18,'Discrete Mathematics'),(58,'Distributed Systems'),(41,'Docker'),(62,'Embedded Systems'),(55,'Ethical Hacking'),(22,'Frontend Development'),(24,'Full Stack Development'),(50,'Generative AI'),(26,'Git & GitHub'),(7,'Go'),(40,'Google Cloud'),(28,'HTML'),(61,'Internet of Things'),(3,'Java'),(5,'JavaScript'),(42,'Kubernetes'),(51,'Large Language Models'),(27,'Linux'),(46,'Machine Learning'),(39,'Microsoft Azure'),(52,'MLOps'),(35,'MongoDB'),(33,'MySQL'),(48,'Natural Language Processing'),(56,'Network Security'),(32,'Next.js'),(31,'Node.js'),(12,'Object-Oriented Programming'),(65,'Open Source'),(14,'Operating Systems'),(59,'Parallel Computing'),(10,'PHP'),(34,'PostgreSQL'),(4,'Python'),(63,'Quantum Computing'),(30,'React'),(36,'Redis'),(25,'REST API Development'),(8,'Rust'),(19,'Software Engineering'),(17,'SQL'),(20,'System Design'),(6,'TypeScript'),(21,'Web Development');
/*!40000 ALTER TABLE `skills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_skills`
--

DROP TABLE IF EXISTS `user_skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_skills` (
  `user_id` int NOT NULL,
  `skill_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`skill_id`),
  KEY `skill_id` (`skill_id`),
  CONSTRAINT `user_skills_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `user_skills_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`skill_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_skills`
--

LOCK TABLES `user_skills` WRITE;
/*!40000 ALTER TABLE `user_skills` DISABLE KEYS */;
INSERT INTO `user_skills` VALUES (29,1),(30,2),(26,3),(28,3),(31,3),(29,4),(26,11),(28,11),(30,11),(31,11),(30,20),(26,21),(28,21),(29,21),(31,65);
/*!40000 ALTER TABLE `user_skills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `profile_photo` varchar(255) DEFAULT 'default.jpg',
  `bio` text,
  `exp_level` enum('Beginner','Intermediate','Expert') DEFAULT NULL,
  `role` enum('trainee','trainer') NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `security_question` varchar(255) DEFAULT NULL,
  `security_answer_hash` varchar(255) DEFAULT NULL,
  `onboarding_completed` tinyint(1) NOT NULL DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_is_admin` (`is_admin`),
  KEY `idx_users_onboarding` (`onboarding_completed`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (8,'SkillConnect Admin','skillconnect.admin@gmail.com','$2y$10$eVCLZweSPmOjhavBU3G5dO4OaqnUjaUrlsA2xKjuZe1sjT83.6bKO','default.jpg',NULL,NULL,'trainer',1,'2026-10-11 20:04:02',NULL,NULL,1,'2026-11-27 06:41:10'),(26,'Wrik','iamwrik@gmail.com','$2y$10$X10eTSOzezeV8UK0QDZtFOJnH.JWVANq/ki/kgtuFtOqDfo6x3xKa','default.jpg','I am wrik.',NULL,'trainee',0,'2026-11-26 18:56:05','school','$2y$10$u2yA2RFWnL8fRngjSbTShORf9zAqwXJs0rEPyY02nqI8ikaQdKSxW',1,'2026-11-27 08:01:41'),(28,'Aman Dhattarwal','amandhattarwal@gmail.com','$2y$10$BBGNvNnFPOrL0Ele4wHmB.P27X038RjRi4DtSY.R0tPgWSXtLTY.i','user_28_6b0921de3f7fa3.91974981.jpg','Founder of Apna College. Teaches DSA and development in simple Hindi with placement-focused roadmaps. Mentored 1M+ students for college placements and internships.','Intermediate','trainer',0,'2026-11-27 06:20:06','school','$2y$10$sin5u.c4V6XtQ31x7Yygw.qgn9vmJf5ewoeHOeUSct9tNberdNI1q',1,NULL),(29,'Harry','codewithharray@gmail.com','$2y$10$.lghTN8FtJR.jzHm7E/Yheg7gnTF5iMW//d0bG1PXEHyMLBMX3K9q','user_29_6b0922b71c1d37.26904350.jpg','One of India\'s most loved coding teachers. Known for 100-day Python course and practical, project-based tutorials in Hindi. Best for absolute beginners.','Expert','trainer',0,'2026-11-27 06:25:11','school','$2y$10$mmzRQwecQvFMH/m4GOUDzOIHQAhuTKnV5psLTjz.PTqH1ubLos9q.',1,NULL),(30,'Raj Vikramaditya','raj.striver@gmail.com','$2y$10$yCCJaesjHuCUeT/juCVrLuwTXvnKbdkWJShJ26bGvoaP0v5Wtv9ui','user_30_6b09246d479b48.79233728.jpg','Creator of Striver\'s SDE Sheet and A2Z DSA Course. Ex-Google. Best known for structured DSA sheets that helped thousands crack product-based companies.','Expert','trainer',0,'2026-11-27 06:31:02','school','$2y$10$KQlPRk4WInpc5Vhn27i9OewNuTWQ0YWi8d6ycwRQHK5jgRkZADfzy',1,NULL),(31,'Kunal Kushwaha','kunal.k@gmail.com','$2y$10$VjZ94IorGzVS4xhxhVgzYucqKAO6wZsoq3maJ2m.8zdo/3hRpOIha','user_31_6b0925f5de4ee2.44094479.jpg','Ex-Goldman Sachs, community builder. Teaches DevOps, Docker, Kubernetes, and open-source contribution with real-world projects. Great for students who want to build in public.','Expert','trainer',0,'2026-11-27 06:35:45','school','$2y$10$3ZHRjBW6So46Lpm0OK77buOBIiW5Imia5YQqo98Ze/fFdXe4Wr5Di',1,'2026-11-27 06:50:42');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-27 14:00:50
