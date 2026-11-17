-- MySQL dump 10.13  Distrib 8.4.7, for Win64 (x86_64)
--
-- Host: localhost    Database: library_management
-- ------------------------------------------------------
-- Server version	8.0.46

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
-- Current Database: `library_management`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `library_management` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `library_management`;

--
-- Table structure for table `authors`
--

DROP TABLE IF EXISTS `authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `authors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `authors`
--

LOCK TABLES `authors` WRITE;
/*!40000 ALTER TABLE `authors` DISABLE KEYS */;
INSERT INTO `authors` VALUES (1,'Robert C. Martin'),(2,'Thomas H. Cormen'),(3,'Abraham Silberschatz'),(4,'Martin Fowler'),(5,'Andrew S. Tanenbaum'),(6,'Brian W. Kernighan'),(7,'Dennis Ritchie'),(8,'James Gosling'),(9,'Donald Knuth'),(10,'Ramez Elmasri'),(11,'Ian Sommerville'),(12,'Eric Matthes'),(13,'Jon Duckett'),(14,'Joshua Bloch'),(15,'Herbert Schildt');
/*!40000 ALTER TABLE `authors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `book_copies`
--

DROP TABLE IF EXISTS `book_copies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `book_copies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `book_id` int NOT NULL,
  `barcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `fk_copies_book` (`book_id`),
  CONSTRAINT `fk_copies_book` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `book_copies`
--

LOCK TABLES `book_copies` WRITE;
/*!40000 ALTER TABLE `book_copies` DISABLE KEYS */;
INSERT INTO `book_copies` VALUES (1,1,'LIB-001'),(2,1,'LIB-002'),(3,2,'LIB-003'),(4,2,'LIB-004'),(5,3,'LIB-005'),(6,3,'LIB-006'),(7,4,'LIB-007'),(8,4,'LIB-008'),(9,5,'LIB-009'),(10,5,'LIB-010'),(11,6,'LIB-011'),(12,6,'LIB-012'),(13,7,'LIB-013'),(14,7,'LIB-014'),(15,8,'LIB-015'),(16,8,'LIB-016'),(17,9,'LIB-017'),(18,9,'LIB-018'),(19,10,'LIB-019'),(20,10,'LIB-020'),(21,11,'LIB-021'),(22,12,'LIB-022'),(23,13,'LIB-023'),(24,14,'LIB-024'),(25,15,'LIB-025'),(26,16,'LIB-026'),(27,17,'LIB-027'),(28,18,'LIB-028'),(29,19,'LIB-029'),(30,20,'LIB-030');
/*!40000 ALTER TABLE `book_copies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `books`
--

DROP TABLE IF EXISTS `books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `books` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isbn` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_id` int NOT NULL,
  `category_id` int NOT NULL,
  `publisher` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publication_year` smallint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `isbn` (`isbn`),
  KEY `fk_books_author` (`author_id`),
  KEY `fk_books_category` (`category_id`),
  CONSTRAINT `fk_books_author` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_books_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `books`
--

LOCK TABLES `books` WRITE;
/*!40000 ALTER TABLE `books` DISABLE KEYS */;
INSERT INTO `books` VALUES (1,'Clean Code','9780132350884',1,10,'Prentice Hall',2008),(2,'Introduction to Algorithms','9780262046305',2,2,'MIT Press',2022),(3,'Database System Concepts','9780078022159',3,3,'McGraw Hill',2019),(4,'Refactoring','9780134757599',4,10,'Addison Wesley',2018),(5,'Computer Networks','9780132126953',5,8,'Pearson',2010),(6,'The C Programming Language','9780131103627',6,1,'Prentice Hall',1988),(7,'C Programming','9780131101630',7,1,'Prentice Hall',1988),(8,'Java: The Complete Reference','9781260440232',8,1,'McGraw Hill',2018),(9,'The Art of Computer Programming','9780201896831',9,2,'Addison Wesley',1997),(10,'Fundamentals of Database Systems','9780133970777',10,3,'Pearson',2015),(11,'Software Engineering','9780137035151',11,10,'Pearson',2015),(12,'Python Crash Course','9781718502703',12,1,'No Starch Press',2023),(13,'HTML and CSS','9781118008188',13,4,'Wiley',2011),(14,'Effective Java','9780134685991',14,1,'Addison Wesley',2018),(15,'Java Programming','9781260463416',15,1,'McGraw Hill',2021),(16,'Artificial Intelligence','9780134610993',2,5,'Pearson',2020),(17,'Machine Learning Basics','9781492052012',12,6,'O Reilly',2020),(18,'Data Science Handbook','9781491912058',10,7,'O Reilly',2017),(19,'Operating Systems Concepts','9781119800361',3,11,'Wiley',2021),(20,'Computer Security','9780134794105',5,9,'Pearson',2018);
/*!40000 ALTER TABLE `books` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Programming','Programming languages and concepts'),(2,'Algorithms','Algorithms and problem solving'),(3,'Database','Database management systems'),(4,'Web Development','Frontend and backend development'),(5,'Artificial Intelligence','AI and intelligent systems'),(6,'Machine Learning','Machine learning concepts'),(7,'Data Science','Data analysis and statistics'),(8,'Networking','Computer networking'),(9,'Cyber Security','Information and network security'),(10,'Software Engineering','Software design and development'),(11,'Operating Systems','Operating system concepts'),(12,'Mathematics','Mathematical concepts'),(13,'Computer Science','General computer science'),(14,'Mobile Development','Mobile application development'),(15,'Cloud Computing','Cloud platforms and services');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loans`
--

DROP TABLE IF EXISTS `loans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `member_id` int NOT NULL,
  `copy_id` int NOT NULL,
  `issued_by` int NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `returned_at` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_loans_member` (`member_id`),
  KEY `idx_loans_copy` (`copy_id`),
  KEY `idx_loans_issued_by` (`issued_by`),
  CONSTRAINT `fk_loans_copy` FOREIGN KEY (`copy_id`) REFERENCES `book_copies` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_loans_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_loans_user` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_loan_dates` CHECK ((`due_date` >= `issue_date`)),
  CONSTRAINT `chk_return_date` CHECK (((`returned_at` is null) or (`returned_at` >= `issue_date`)))
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loans`
--

LOCK TABLES `loans` WRITE;
/*!40000 ALTER TABLE `loans` DISABLE KEYS */;
INSERT INTO `loans` VALUES (57,7,19,81,'2026-11-17','2026-12-01',NULL);
/*!40000 ALTER TABLE `loans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `membership_date` date NOT NULL,
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `members`
--

LOCK TABLES `members` WRITE;
/*!40000 ALTER TABLE `members` DISABLE KEYS */;
INSERT INTO `members` VALUES (1,'Ali Ahmed','ali@example.com','03001234567','Rawalpindi','2026-11-01','Active','2026-11-16 21:22:11'),(2,'Sara Khan','sara@example.com','03007654321','Islamabad','2026-11-02','Active','2026-11-16 21:22:11'),(3,'Usman Malik','usman@example.com','03001112222','Rawalpindi','2026-11-03','Active','2026-11-16 21:22:11'),(4,'Ayesha Noor','ayesha@example.com','03002223333','Islamabad','2026-11-04','Active','2026-11-16 21:22:11'),(5,'Hamza Ali','hamza@example.com','03003334444','Lahore','2026-11-05','Active','2026-11-16 21:22:11'),(6,'Fatima Zahra','fatima@example.com','03004445555','Karachi','2026-11-06','Active','2026-11-16 21:22:11'),(7,'Ahmed Raza','ahmed@example.com','03005556666','Rawalpindi','2026-11-07','Active','2026-11-16 21:22:11'),(8,'Hassan Shah','hassan@example.com','03006667777','Islamabad','2026-11-08','Active','2026-11-16 21:22:11'),(9,'Zainab Iqbal','zainab@example.com','03007778888','Lahore','2026-11-09','Active','2026-11-16 21:22:11'),(10,'Bilal Khan','bilal@example.com','03008889999','Peshawar','2026-11-10','Active','2026-11-16 21:22:11'),(11,'Maryam Aslam','maryam@example.com','03009990000','Multan','2026-11-11','Active','2026-11-16 21:22:11'),(12,'Daniyal Ahmed','daniyal@example.com','03001239876','Karachi','2026-11-12','Active','2026-11-16 21:22:11'),(13,'Hira Faisal','hira@example.com','03002345678','Islamabad','2026-11-13','Active','2026-11-16 21:22:11'),(14,'Saad Mahmood','saad@example.com','03003456789','Lahore','2026-11-14','Inactive','2026-11-16 21:22:11'),(15,'Noor Fatima','noor@example.com','03004567890','Rawalpindi','2026-11-15','Active','2026-11-16 21:22:11');
/*!40000 ALTER TABLE `members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('Admin','Librarian') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Librarian',
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (6,'Zahid Ali','zahid123@gmail.com','$2y$10$14DR.x1BYt1nVnve7.WKb.w4cDwIZ6L7xPub0O/rrR4kRx9RHanVa','Admin','Active','2026-11-17 07:48:18'),(81,'Faizan Ali','faizan123@gmail.com','$2y$10$dyVea02d6Zppf3R5CSQ.HeMjobgvoa4EPl7d7MsRr2Epj717lbKAS','Librarian','Active','2026-11-17 12:05:28');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'library_management'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-17 17:48:59
