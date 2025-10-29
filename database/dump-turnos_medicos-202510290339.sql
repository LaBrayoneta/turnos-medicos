-- MySQL dump 10.13  Distrib 8.0.19, for Win64 (x86_64)
--
-- Host: localhost    Database: turnos_medicos
-- ------------------------------------------------------
-- Server version	8.4.3

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
-- Table structure for table `especialidad`
--

DROP TABLE IF EXISTS `especialidad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `especialidad` (
  `Id_Especialidad` int NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`Id_Especialidad`),
  UNIQUE KEY `nombre_UNIQUE` (`Nombre`),
  KEY `idx_activo` (`Activo`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `especialidad`
--

LOCK TABLES `especialidad` WRITE;
/*!40000 ALTER TABLE `especialidad` DISABLE KEYS */;
INSERT INTO `especialidad` VALUES (1,'Clínica Médica','Atención médica general',1),(2,'Pediatría','Atención pediátrica',1),(3,'Cardiología','Enfermedades cardiovasculares',1),(4,'Traumatología','Sistema músculo-esquelético',1),(5,'Ginecología','Salud de la mujer',1),(6,'Dermatología','Enfermedades de la piel',1),(7,'Oftalmología','Enfermedades oculares',1);
/*!40000 ALTER TABLE `especialidad` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horario_medico`
--

DROP TABLE IF EXISTS `horario_medico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `horario_medico` (
  `Id_horario` int NOT NULL AUTO_INCREMENT,
  `Id_medico` int NOT NULL,
  `Dia_semana` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Hora_inicio` time NOT NULL,
  `Hora_fin` time NOT NULL,
  PRIMARY KEY (`Id_horario`),
  KEY `idx_medico_dia` (`Id_medico`,`Dia_semana`),
  CONSTRAINT `fk_horario_medico` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horario_medico`
--

LOCK TABLES `horario_medico` WRITE;
/*!40000 ALTER TABLE `horario_medico` DISABLE KEYS */;
INSERT INTO `horario_medico` VALUES (1,3,'lunes','08:00:00','12:00:00'),(2,3,'lunes','14:00:00','18:00:00'),(3,3,'miercoles','09:00:00','13:00:00'),(4,3,'viernes','08:00:00','12:00:00'),(5,4,'martes','10:00:00','14:00:00'),(6,4,'jueves','10:00:00','14:00:00'),(7,4,'jueves','16:00:00','20:00:00'),(8,5,'lunes','14:00:00','18:00:00'),(9,5,'martes','14:00:00','18:00:00'),(10,5,'miercoles','14:00:00','18:00:00'),(11,5,'jueves','14:00:00','18:00:00'),(12,5,'viernes','14:00:00','18:00:00'),(13,3,'lunes','08:00:00','12:00:00'),(14,3,'lunes','17:00:00','21:00:00');
/*!40000 ALTER TABLE `horario_medico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medico`
--

DROP TABLE IF EXISTS `medico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medico` (
  `Id_medico` int NOT NULL AUTO_INCREMENT,
  `Legajo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Id_usuario` int NOT NULL,
  `Id_Especialidad` int NOT NULL,
  `Dias_Disponibles` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'lunes,martes,miercoles,jueves,viernes',
  `Hora_Inicio` time DEFAULT '08:00:00',
  `Hora_Fin` time DEFAULT '16:00:00',
  `Duracion_Turno` int DEFAULT '30',
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`Id_medico`),
  UNIQUE KEY `uniq_medico_usuario` (`Id_usuario`),
  UNIQUE KEY `legajo_UNIQUE` (`Legajo`),
  KEY `medico_usuario_FK` (`Id_usuario`),
  KEY `medico_especialidad_FK` (`Id_Especialidad`),
  KEY `idx_activo` (`Activo`),
  KEY `idx_especialidad_activo` (`Id_Especialidad`,`Activo`),
  CONSTRAINT `medico_especialidad_FK` FOREIGN KEY (`Id_Especialidad`) REFERENCES `especialidad` (`Id_Especialidad`) ON DELETE RESTRICT,
  CONSTRAINT `medico_usuario_FK` FOREIGN KEY (`Id_usuario`) REFERENCES `usuario` (`Id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medico`
--

LOCK TABLES `medico` WRITE;
/*!40000 ALTER TABLE `medico` DISABLE KEYS */;
INSERT INTO `medico` VALUES (3,'195-b',18,3,'lunes,viernes','12:00:00','14:00:00',30,1),(4,'12',20,5,'lunes,martes,miercoles,jueves,viernes','19:00:00','14:00:00',30,1),(5,'122',22,2,'lunes,martes,miercoles,jueves,viernes','14:00:00','22:00:00',30,1);
/*!40000 ALTER TABLE `medico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `obra_social`
--

DROP TABLE IF EXISTS `obra_social`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `obra_social` (
  `Id_obra_social` int NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `Fecha_Alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_obra_social`),
  UNIQUE KEY `nombre_UNIQUE` (`Nombre`),
  KEY `idx_activo` (`Activo`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `obra_social`
--

LOCK TABLES `obra_social` WRITE;
/*!40000 ALTER TABLE `obra_social` DISABLE KEYS */;
INSERT INTO `obra_social` VALUES (1,'OSPROTURA',1,'2025-10-28 00:10:15'),(2,'OSMISS',1,'2025-10-28 00:10:15'),(3,'OSTCARA',1,'2025-10-28 00:10:15'),(4,'OSAMOC',1,'2025-10-28 00:10:15'),(5,'OSTAXBA',1,'2025-10-28 00:10:15'),(6,'OSDE',1,'2025-10-28 00:10:15'),(7,'Swiss Medical',1,'2025-10-28 00:10:15'),(8,'PAMI',1,'2025-10-28 00:10:15'),(9,'Sin obra social',1,'2025-10-28 00:10:15');
/*!40000 ALTER TABLE `obra_social` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paciente`
--

DROP TABLE IF EXISTS `paciente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paciente` (
  `Id_paciente` int NOT NULL AUTO_INCREMENT,
  `Id_obra_social` int DEFAULT NULL,
  `Nro_carnet` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Obra_social` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Sin obra social',
  `Libreta_sanitaria` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N/A',
  `Id_usuario` int NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `Fecha_Alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_paciente`),
  UNIQUE KEY `uniq_paciente_usuario` (`Id_usuario`),
  KEY `paciente_usuario_FK` (`Id_usuario`),
  KEY `idx_activo` (`Activo`),
  KEY `fk_paciente_obra_social` (`Id_obra_social`),
  CONSTRAINT `fk_paciente_obra_social` FOREIGN KEY (`Id_obra_social`) REFERENCES `obra_social` (`Id_obra_social`) ON DELETE SET NULL,
  CONSTRAINT `paciente_usuario_FK` FOREIGN KEY (`Id_usuario`) REFERENCES `usuario` (`Id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paciente`
--

LOCK TABLES `paciente` WRITE;
/*!40000 ALTER TABLE `paciente` DISABLE KEYS */;
INSERT INTO `paciente` VALUES (9,6,NULL,'OSDE','6789',19,1,'2025-10-24 00:39:30'),(10,NULL,NULL,'Osdes','2222',23,1,'2025-10-24 03:33:31'),(11,NULL,NULL,'Osdes','maximo.teuber1234@gmail.com',24,1,'2025-10-24 03:36:47'),(12,1,NULL,'OSPROTURA','3444',25,1,'2025-10-24 03:48:49'),(13,NULL,NULL,'Osdes','3444',26,1,'2025-10-24 05:09:37');
/*!40000 ALTER TABLE `paciente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `secretaria`
--

DROP TABLE IF EXISTS `secretaria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `secretaria` (
  `Id_secretaria` int NOT NULL AUTO_INCREMENT,
  `Id_usuario` int NOT NULL,
  `Activo` tinyint(1) NOT NULL DEFAULT '1',
  `Fecha_Alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_secretaria`),
  UNIQUE KEY `uniq_secretaria_usuario` (`Id_usuario`),
  KEY `secretaria_usuario_FK` (`Id_usuario`),
  KEY `idx_activo` (`Activo`),
  CONSTRAINT `secretaria_usuario_FK` FOREIGN KEY (`Id_usuario`) REFERENCES `usuario` (`Id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `secretaria`
--

LOCK TABLES `secretaria` WRITE;
/*!40000 ALTER TABLE `secretaria` DISABLE KEYS */;
INSERT INTO `secretaria` VALUES (4,14,1,'2025-10-24 00:30:00'),(5,17,1,'2025-10-24 00:35:40');
/*!40000 ALTER TABLE `secretaria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `turno`
--

DROP TABLE IF EXISTS `turno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `turno` (
  `Id_turno` int NOT NULL AUTO_INCREMENT,
  `Fecha` datetime NOT NULL,
  `Estado` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'reservado',
  `Id_paciente` int NOT NULL,
  `Id_medico` int NOT NULL,
  `Id_secretaria` int DEFAULT NULL,
  `Observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `Fecha_Creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `Fecha_Modificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_turno`),
  KEY `turno_paciente_FK` (`Id_paciente`),
  KEY `turno_medico_FK` (`Id_medico`),
  KEY `turno_secretaria_FK` (`Id_secretaria`),
  KEY `idx_fecha` (`Fecha`),
  KEY `idx_estado` (`Estado`),
  KEY `idx_medico_fecha` (`Id_medico`,`Fecha`),
  KEY `idx_paciente_fecha` (`Id_paciente`,`Fecha`),
  CONSTRAINT `turno_medico_FK` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT,
  CONSTRAINT `turno_paciente_FK` FOREIGN KEY (`Id_paciente`) REFERENCES `paciente` (`Id_paciente`) ON DELETE RESTRICT,
  CONSTRAINT `turno_secretaria_FK` FOREIGN KEY (`Id_secretaria`) REFERENCES `secretaria` (`Id_secretaria`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `turno`
--

LOCK TABLES `turno` WRITE;
/*!40000 ALTER TABLE `turno` DISABLE KEYS */;
INSERT INTO `turno` VALUES (1,'2025-10-28 16:30:00','reservado',10,5,NULL,NULL,'2025-10-24 03:33:42','2025-10-24 03:33:42'),(2,'2025-10-28 16:00:00','reservado',11,5,NULL,NULL,'2025-10-24 03:36:59','2025-10-24 03:36:59'),(3,'2025-10-28 17:30:00','reservado',12,5,NULL,NULL,'2025-10-24 03:49:30','2025-10-24 03:51:28'),(4,'2025-10-27 14:30:00','reservado',13,5,NULL,NULL,'2025-10-24 05:09:46','2025-10-24 05:09:46'),(5,'2025-10-24 21:00:00','reservado',9,5,4,NULL,'2025-10-24 05:47:50','2025-10-24 05:47:50'),(6,'2025-10-28 18:30:00','reservado',9,5,4,NULL,'2025-10-24 22:38:43','2025-10-24 22:38:43');
/*!40000 ALTER TABLE `turno` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `Id_usuario` int NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Apellido` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dni` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Contraseña` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Rol` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `Fecha_Registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_usuario`),
  UNIQUE KEY `email_UNIQUE` (`email`),
  UNIQUE KEY `dni_UNIQUE` (`dni`),
  KEY `idx_rol` (`Rol`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (14,'Maria','Gonzalez','48165176','secretaria@clinica.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','secretaria','2025-10-24 00:30:00'),(17,'lucia','gomez','48176154','pepe@gmail.com','$2y$10$bL5cJeA9ZIu1OxcgTpM/WOQIuXpn2ee.ev69S/RvmXv6Al3Kyj.Xq','secretaria','2025-10-24 00:35:40'),(18,'braian','salgado','4867585','braiansalgado2436@gmail.com','$2y$10$6j81cIFfIjP6n6Ia3gFMkeQnVb81GW0kZP/2Z4lKd9fZzQ8Gbr7i.','medico','2025-10-24 00:36:45'),(19,'Juan','Perez','48234567','sadafabcsafa@gmail.com','$2y$12$gzniafBVGmltUPva6ovmfeh9R4zK4gmOqvwrId5Vz63LhHxvcrRCe','paciente','2025-10-24 00:39:30'),(20,'MAXIMO','TEUBER','47516428','maximo.teuber1234@gmail.com','$2y$10$kwZsmwMkevIXfeJG0R5j3uxsfcBoXDqzb8emxRxpO9e5bHI3ogS8y','medico','2025-10-24 02:38:21'),(22,'MAXIMO','TEUBERR','12345679','maximo.teuber12345@gmail.com','$2y$10$g9PNxyEbYDw5e5ND9BmBoOlR3uBzy.Kg73bk8J5rUFHs1H4ubHuma','medico','2025-10-24 03:31:17'),(23,'pepe','perez','47516429','maximo.teuber1@gmail.com','$2y$12$WqTg0aSP/o0hnRHXojgWmucPuxkeO1QLk.HB06x16UUsPj1vjVWee','paciente','2025-10-24 03:33:31'),(24,'pepe','rodriguez','47516427','maximo.teuber124@gmail.com','$2y$12$ciB/EGd./c02mBuUqv7BiuvjGOHsUGo7z8dkiVxpiXv5XM/lnwMqW','paciente','2025-10-24 03:36:47'),(25,'Aquiles','Castro','87654321','aquiles@gmail.com','$2y$12$dr.TS0OT/TdqrL.GC76T9OPFN0jNgOrKtG8QiP1Rra0PnxgPjoDUu','paciente','2025-10-24 03:48:49'),(26,'ramiro','vidal','47516422','maximo.teuber12223@gmail.com','$2y$12$8ya7sukt3BDkLPGWddnKde.pLPWNoPXIWw4NQRR5xwQs32cRGQJfq','paciente','2025-10-24 05:09:37');
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'turnos_medicos'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-29  3:39:07
