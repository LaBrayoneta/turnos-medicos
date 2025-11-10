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
-- Table structure for table `diagnostico`
--

DROP TABLE IF EXISTS `diagnostico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `diagnostico` (
  `Id_diagnostico` int NOT NULL AUTO_INCREMENT,
  `Id_turno` int NOT NULL,
  `Id_medico` int NOT NULL,
  `Diagnostico` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Diagnóstico médico principal',
  `Observaciones` text COLLATE utf8mb4_unicode_ci COMMENT 'Observaciones y notas adicionales',
  `Sintomas` text COLLATE utf8mb4_unicode_ci COMMENT 'Síntomas reportados por el paciente',
  `Fecha_Diagnostico` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_diagnostico`),
  KEY `idx_turno` (`Id_turno`),
  KEY `idx_medico` (`Id_medico`),
  KEY `idx_fecha` (`Fecha_Diagnostico`),
  CONSTRAINT `fk_diagnostico_medico` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT,
  CONSTRAINT `fk_diagnostico_turno` FOREIGN KEY (`Id_turno`) REFERENCES `turno` (`Id_turno`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Diagnósticos médicos por turno';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `diagnostico`
--

LOCK TABLES `diagnostico` WRITE;
/*!40000 ALTER TABLE `diagnostico` DISABLE KEYS */;
/*!40000 ALTER TABLE `diagnostico` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Table structure for table `historial_clinico`
--

DROP TABLE IF EXISTS `historial_clinico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `historial_clinico` (
  `Id_historial` int NOT NULL AUTO_INCREMENT,
  `Id_paciente` int NOT NULL,
  `Id_turno` int DEFAULT NULL,
  `Id_medico` int NOT NULL,
  `Tipo_Registro` enum('consulta','diagnostico','receta','nota','estudio') COLLATE utf8mb4_unicode_ci DEFAULT 'nota',
  `Contenido` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Fecha_Registro` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_historial`),
  KEY `idx_paciente` (`Id_paciente`),
  KEY `idx_turno` (`Id_turno`),
  KEY `idx_medico` (`Id_medico`),
  KEY `idx_fecha` (`Fecha_Registro`),
  KEY `idx_historial_tipo` (`Tipo_Registro`,`Fecha_Registro`),
  CONSTRAINT `fk_historial_medico` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT,
  CONSTRAINT `fk_historial_paciente` FOREIGN KEY (`Id_paciente`) REFERENCES `paciente` (`Id_paciente`) ON DELETE CASCADE,
  CONSTRAINT `fk_historial_turno` FOREIGN KEY (`Id_turno`) REFERENCES `turno` (`Id_turno`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial clínico completo del paciente';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_clinico`
--

LOCK TABLES `historial_clinico` WRITE;
/*!40000 ALTER TABLE `historial_clinico` DISABLE KEYS */;
/*!40000 ALTER TABLE `historial_clinico` ENABLE KEYS */;
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
  KEY `idx_horario_dia` (`Dia_semana`,`Hora_inicio`),
  CONSTRAINT `fk_horario_medico` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horario_medico`
--

LOCK TABLES `horario_medico` WRITE;
/*!40000 ALTER TABLE `horario_medico` DISABLE KEYS */;
INSERT INTO `horario_medico` VALUES (5,4,'martes','10:00:00','14:00:00'),(6,4,'jueves','10:00:00','14:00:00'),(7,4,'jueves','16:00:00','20:00:00'),(15,6,'jueves','08:00:00','12:00:00'),(16,6,'jueves','16:00:00','21:00:00'),(17,3,'viernes','08:00:00','12:00:00'),(18,5,'lunes','08:00:00','12:00:00'),(19,7,'martes','10:00:00','12:00:00'),(20,7,'martes','17:00:00','22:00:00');
/*!40000 ALTER TABLE `horario_medico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicamento`
--

DROP TABLE IF EXISTS `medicamento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medicamento` (
  `Id_medicamento` int NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Principio_Activo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Presentacion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Dosis_Usual` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Activo` tinyint(1) DEFAULT '1',
  `Fecha_Alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_medicamento`),
  KEY `idx_nombre` (`Nombre`),
  KEY `idx_activo` (`Activo`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de medicamentos disponibles';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicamento`
--

LOCK TABLES `medicamento` WRITE;
/*!40000 ALTER TABLE `medicamento` DISABLE KEYS */;
INSERT INTO `medicamento` VALUES (1,'Ibuprofeno 400mg','Ibuprofeno','Comprimidos x 30','1 comp. cada 8hs',1,'2025-11-08 07:24:57'),(2,'Paracetamol 500mg','Paracetamol','Comprimidos x 30','1 comp. cada 6-8hs',1,'2025-11-08 07:24:57'),(3,'Amoxicilina 500mg','Amoxicilina','Cápsulas x 21','1 cáps. cada 8hs',1,'2025-11-08 07:24:57'),(4,'Omeprazol 20mg','Omeprazol','Cápsulas x 28','1 cáps. en ayunas',1,'2025-11-08 07:24:57'),(5,'Losartán 50mg','Losartán','Comprimidos x 30','1 comp. por día',1,'2025-11-08 07:24:57'),(6,'Enalapril 10mg','Enalapril','Comprimidos x 30','1 comp. por día',1,'2025-11-08 07:24:57'),(7,'Metformina 850mg','Metformina','Comprimidos x 60','1 comp. cada 12hs',1,'2025-11-08 07:24:57'),(8,'Atorvastatina 20mg','Atorvastatina','Comprimidos x 30','1 comp. por noche',1,'2025-11-08 07:24:57'),(9,'Salbutamol 100mcg','Salbutamol','Aerosol 200 dosis','2 puff cada 6-8hs',1,'2025-11-08 07:24:57'),(10,'Loratadina 10mg','Loratadina','Comprimidos x 30','1 comp. por día',1,'2025-11-08 07:24:57');
/*!40000 ALTER TABLE `medicamento` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medico`
--

LOCK TABLES `medico` WRITE;
/*!40000 ALTER TABLE `medico` DISABLE KEYS */;
INSERT INTO `medico` VALUES (3,'195-b',18,3,30,1),(4,'12',20,5,30,1),(5,'122',22,2,30,1),(6,'195-d',27,4,30,1),(7,'195-e',30,5,30,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `obra_social`
--

LOCK TABLES `obra_social` WRITE;
/*!40000 ALTER TABLE `obra_social` DISABLE KEYS */;
INSERT INTO `obra_social` VALUES (1,'OSPROTURA',1,'2025-10-28 00:10:15'),(2,'OSMISS',1,'2025-10-28 00:10:15'),(3,'OSTCARA',1,'2025-10-28 00:10:15'),(4,'OSAMOC',1,'2025-10-28 00:10:15'),(5,'OSTAXBA',1,'2025-10-28 00:10:15'),(6,'OSDE',1,'2025-10-28 00:10:15'),(7,'Swiss Medical',1,'2025-10-28 00:10:15'),(9,'Sin obra social',1,'2025-10-28 00:10:15'),(10,'PAMI',1,'2025-10-29 23:03:02');
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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paciente`
--

LOCK TABLES `paciente` WRITE;
/*!40000 ALTER TABLE `paciente` DISABLE KEYS */;
INSERT INTO `paciente` VALUES (9,6,NULL,'6789',19,1,'2025-10-24 00:39:30'),(10,NULL,NULL,'2222',23,1,'2025-10-24 03:33:31'),(11,NULL,NULL,'maximo.teuber1234@gmail.com',24,1,'2025-10-24 03:36:47'),(12,1,NULL,'3444',25,1,'2025-10-24 03:48:49'),(13,NULL,NULL,'3444',26,1,'2025-10-24 05:09:37'),(14,3,'12345657443','48165176',28,1,'2025-10-29 07:13:04'),(15,9,NULL,'12345',29,1,'2025-10-29 07:51:37'),(16,7,'12314124124','123123124241',32,1,'2025-10-29 23:31:04');
/*!40000 ALTER TABLE `paciente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receta`
--

DROP TABLE IF EXISTS `receta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `receta` (
  `Id_receta` int NOT NULL AUTO_INCREMENT,
  `Id_diagnostico` int NOT NULL,
  `Id_medico` int NOT NULL,
  `Id_paciente` int NOT NULL,
  `Medicamentos` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Lista de medicamentos recetados',
  `Indicaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `Duracion_Tratamiento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Duración del tratamiento (ej: 7 días)',
  `Fecha_Receta` datetime DEFAULT CURRENT_TIMESTAMP,
  `Fecha_Vencimiento` date DEFAULT NULL COMMENT 'Fecha de vencimiento de la receta',
  PRIMARY KEY (`Id_receta`),
  KEY `idx_diagnostico` (`Id_diagnostico`),
  KEY `idx_medico` (`Id_medico`),
  KEY `idx_paciente` (`Id_paciente`),
  KEY `idx_fecha` (`Fecha_Receta`),
  KEY `idx_receta_medico_fecha` (`Id_medico`,`Fecha_Receta`),
  CONSTRAINT `fk_receta_diagnostico` FOREIGN KEY (`Id_diagnostico`) REFERENCES `diagnostico` (`Id_diagnostico`) ON DELETE CASCADE,
  CONSTRAINT `fk_receta_medico` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT,
  CONSTRAINT `fk_receta_paciente` FOREIGN KEY (`Id_paciente`) REFERENCES `paciente` (`Id_paciente`) ON DELETE RESTRICT,
  CONSTRAINT `chk_fecha_vencimiento` CHECK (((`Fecha_Vencimiento` is null) or (`Fecha_Vencimiento` >= cast(`Fecha_Receta` as date))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Recetas médicas asociadas a diagnósticos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receta`
--

LOCK TABLES `receta` WRITE;
/*!40000 ALTER TABLE `receta` DISABLE KEYS */;
/*!40000 ALTER TABLE `receta` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `secretaria`
--

LOCK TABLES `secretaria` WRITE;
/*!40000 ALTER TABLE `secretaria` DISABLE KEYS */;
INSERT INTO `secretaria` VALUES (5,17,0,'2025-10-24 00:35:40'),(6,31,1,'2025-10-29 23:02:08');
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
  `Atendido` tinyint(1) DEFAULT '0' COMMENT 'Estado de atención: 0=Pendiente, 1=Atendido',
  `Fecha_Atencion` datetime DEFAULT NULL COMMENT 'Fecha y hora en que fue atendido',
  `Id_medico_atencion` int DEFAULT NULL COMMENT 'Médico que realizó la atención',
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
  KEY `idx_turno_fecha_medico_estado` (`Fecha`,`Id_medico`,`Estado`),
  KEY `idx_turno_medico_fecha_estado` (`Id_medico`,`Fecha`,`Estado`),
  KEY `fk_turno_medico_atencion` (`Id_medico_atencion`),
  KEY `idx_turno_atendido` (`Atendido`,`Fecha`),
  KEY `idx_turno_paciente_medico_fecha` (`Id_paciente`,`Id_medico`,`Fecha`),
  KEY `idx_fecha_atencion` (`Fecha_Atencion`),
  CONSTRAINT `fk_turno_medico_atencion` FOREIGN KEY (`Id_medico_atencion`) REFERENCES `medico` (`Id_medico`) ON DELETE SET NULL,
  CONSTRAINT `turno_medico_FK` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT,
  CONSTRAINT `turno_paciente_FK` FOREIGN KEY (`Id_paciente`) REFERENCES `paciente` (`Id_paciente`) ON DELETE RESTRICT,
  CONSTRAINT `turno_secretaria_FK` FOREIGN KEY (`Id_secretaria`) REFERENCES `secretaria` (`Id_secretaria`) ON DELETE SET NULL,
  CONSTRAINT `chk_atendido` CHECK ((`Atendido` in (0,1))),
  CONSTRAINT `chk_fecha_atencion` CHECK (((`Fecha_Atencion` is null) or (`Fecha_Atencion` >= `Fecha`)))
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `turno`
--

LOCK TABLES `turno` WRITE;
/*!40000 ALTER TABLE `turno` DISABLE KEYS */;
INSERT INTO `turno` VALUES (1,'2025-10-28 16:30:00','vencido',0,NULL,NULL,10,5,NULL,NULL,'2025-10-24 03:33:42','2025-11-10 01:34:13'),(2,'2025-10-28 16:00:00','vencido',0,NULL,NULL,11,5,NULL,NULL,'2025-10-24 03:36:59','2025-11-10 01:34:13'),(3,'2025-10-28 17:30:00','vencido',0,NULL,NULL,12,5,NULL,NULL,'2025-10-24 03:49:30','2025-11-10 01:34:13'),(4,'2025-10-27 14:30:00','vencido',0,NULL,NULL,13,5,NULL,NULL,'2025-10-24 05:09:46','2025-11-10 01:34:13'),(5,'2025-10-24 21:00:00','vencido',0,NULL,NULL,9,5,NULL,NULL,'2025-10-24 05:47:50','2025-11-10 01:34:13'),(6,'2025-10-28 18:30:00','vencido',0,NULL,NULL,9,5,NULL,NULL,'2025-10-24 22:38:43','2025-11-10 01:34:13'),(7,'2025-11-21 10:00:00','cancelado',0,NULL,NULL,11,3,NULL,NULL,'2025-10-29 06:51:51','2025-10-29 22:25:51'),(8,'2025-11-06 20:30:00','vencido',0,NULL,NULL,15,6,NULL,NULL,'2025-10-29 07:51:56','2025-11-10 01:34:13'),(9,'2025-10-31 11:30:00','vencido',0,NULL,NULL,15,3,NULL,NULL,'2025-10-29 22:57:16','2025-11-10 01:34:13'),(10,'2025-11-04 10:00:00','vencido',0,NULL,NULL,13,7,NULL,NULL,'2025-10-29 23:04:41','2025-11-10 01:34:13'),(11,'2025-11-17 10:00:00','cancelado',0,NULL,NULL,15,5,NULL,NULL,'2025-10-29 23:06:17','2025-10-29 23:06:23'),(12,'2025-11-17 10:00:00','reservado',0,NULL,NULL,15,5,NULL,NULL,'2025-10-29 23:06:59','2025-10-29 23:06:59'),(13,'2025-11-21 10:00:00','cancelado',0,NULL,NULL,16,3,NULL,NULL,'2025-10-29 23:31:21','2025-10-29 23:31:40'),(14,'2025-10-31 09:30:00','cancelado',0,NULL,NULL,16,3,NULL,NULL,'2025-10-29 23:40:29','2025-10-29 23:40:31');
/*!40000 ALTER TABLE `turno` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `before_turno_insert` BEFORE INSERT ON `turno` FOR EACH ROW BEGIN
    DECLARE dia_semana VARCHAR(20);
    DECLARE hora_turno TIME;
    DECLARE atiende INT DEFAULT 0;
    DECLARE ya_tiene_turno INT DEFAULT 0;
    
    -- Obtener día de la semana
    SET dia_semana = CASE DAYOFWEEK(NEW.Fecha)
        WHEN 1 THEN 'domingo'
        WHEN 2 THEN 'lunes'
        WHEN 3 THEN 'martes'
        WHEN 4 THEN 'miercoles'
        WHEN 5 THEN 'jueves'
        WHEN 6 THEN 'viernes'
        WHEN 7 THEN 'sabado'
    END;
    
    SET hora_turno = TIME(NEW.Fecha);
    
    -- Verificar que el médico atiende ese día y horario
    SELECT COUNT(*) INTO atiende
    FROM horario_medico
    WHERE Id_medico = NEW.Id_medico
    AND Dia_semana = dia_semana
    AND hora_turno >= Hora_inicio
    AND hora_turno < Hora_fin;
    
    IF atiende = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El médico no atiende en ese horario';
    END IF;
    
    -- Verificar que el paciente no tenga otro turno con el mismo médico ese día
    SELECT COUNT(*) INTO ya_tiene_turno
    FROM turno
    WHERE Id_paciente = NEW.Id_paciente
    AND Id_medico = NEW.Id_medico
    AND DATE(Fecha) = DATE(NEW.Fecha)
    AND (Estado IS NULL OR Estado != 'cancelado');
    
    IF ya_tiene_turno > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El paciente ya tiene un turno con este médico en esta fecha';
    END IF;
    
    -- Establecer valores por defecto
    IF NEW.Estado IS NULL THEN
        SET NEW.Estado = 'reservado';
    END IF;
    
    IF NEW.Atendido IS NULL THEN
        SET NEW.Atendido = 0;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `before_turno_atendido` BEFORE UPDATE ON `turno` FOR EACH ROW BEGIN
    -- Si se marca como atendido, registrar la fecha
    IF NEW.Atendido = 1 AND OLD.Atendido = 0 THEN
        IF NEW.Fecha_Atencion IS NULL THEN
            SET NEW.Fecha_Atencion = NOW();
        END IF;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_turno_atendido` AFTER UPDATE ON `turno` FOR EACH ROW BEGIN
    -- Si se acaba de marcar como atendido
    IF NEW.Atendido = 1 AND OLD.Atendido = 0 THEN
        -- Verificar si ya hay un diagnóstico
        IF EXISTS (SELECT 1 FROM diagnostico WHERE Id_turno = NEW.Id_turno) THEN
            -- Registrar en historial
            INSERT INTO historial_clinico (
                Id_paciente,
                Id_turno,
                Id_medico,
                Tipo_Registro,
                Contenido
            ) VALUES (
                NEW.Id_paciente,
                NEW.Id_turno,
                NEW.Id_medico,
                'consulta',
                CONCAT('Turno atendido el ', DATE_FORMAT(NEW.Fecha_Atencion, '%d/%m/%Y %H:%i'))
            );
        END IF;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

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
  `Contraseña` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Rol` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `Fecha_Registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acceso` datetime DEFAULT NULL,
  PRIMARY KEY (`Id_usuario`),
  UNIQUE KEY `email_UNIQUE` (`email`),
  UNIQUE KEY `dni_UNIQUE` (`dni`),
  KEY `idx_rol` (`Rol`),
  KEY `idx_email` (`email`),
  KEY `idx_usuario_search` (`dni`,`email`,`Nombre`,`Apellido`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (17,'lucia','gomez','48176154','pepe@gmail.com','$2y$10$bL5cJeA9ZIu1OxcgTpM/WOQIuXpn2ee.ev69S/RvmXv6Al3Kyj.Xq','secretaria','2025-10-24 00:35:40',NULL),(18,'braian','salgado','4867585','braiansalgado2436@gmail.com','$2y$10$6j81cIFfIjP6n6Ia3gFMkeQnVb81GW0kZP/2Z4lKd9fZzQ8Gbr7i.','medico','2025-10-24 00:36:45',NULL),(19,'Juan','Perez','48234567','sadafabcsafa@gmail.com','$2y$12$gzniafBVGmltUPva6ovmfeh9R4zK4gmOqvwrId5Vz63LhHxvcrRCe','paciente','2025-10-24 00:39:30',NULL),(20,'MAXIMO','TEUBER','47516428','maximo.teuber1234@gmail.com','$2y$10$kwZsmwMkevIXfeJG0R5j3uxsfcBoXDqzb8emxRxpO9e5bHI3ogS8y','medico','2025-10-24 02:38:21',NULL),(22,'MAXIMO','TEUBERR','12345679','maximo.teuber12345@gmail.com','$2y$10$g9PNxyEbYDw5e5ND9BmBoOlR3uBzy.Kg73bk8J5rUFHs1H4ubHuma','medico','2025-10-24 03:31:17',NULL),(23,'pepe','perez','47516429','maximo.teuber1@gmail.com','$2y$12$WqTg0aSP/o0hnRHXojgWmucPuxkeO1QLk.HB06x16UUsPj1vjVWee','paciente','2025-10-24 03:33:31',NULL),(24,'pepe','rodriguez','47516427','maximo.teuber124@gmail.com','$2y$12$ciB/EGd./c02mBuUqv7BiuvjGOHsUGo7z8dkiVxpiXv5XM/lnwMqW','paciente','2025-10-24 03:36:47',NULL),(25,'Aquiles','Castro','87654321','aquiles@gmail.com','$2y$12$dr.TS0OT/TdqrL.GC76T9OPFN0jNgOrKtG8QiP1Rra0PnxgPjoDUu','paciente','2025-10-24 03:48:49',NULL),(26,'ramiro','vidal','47516422','maximo.teuber12223@gmail.com','$2y$12$8ya7sukt3BDkLPGWddnKde.pLPWNoPXIWw4NQRR5xwQs32cRGQJfq','paciente','2025-10-24 05:09:37',NULL),(27,'Juan','sapienza','48124343','jano@gmail.com','$2y$10$14U6IEN63sGtIClzMIS9leaWNKscUFwRKpRboNhkASX7H9duY.4vG','medico','2025-10-29 06:59:23','2025-11-08 15:20:20'),(28,'ramiro','sapienza','487654321','rami@gmail.com','$2y$12$tkwmCcW7gQBw1fQR5ojRyOyxnOeuEZHSMjx4oOXjinibPXf1Dui3S','paciente','2025-10-29 07:13:04',NULL),(29,'Javier','Milei','12345678','milei@gmail.com','$2y$12$jULrGJM8LQEbIS9GPleU/.CWoBgbYpU32qMXyPHAEycaF.IWEEmbW','paciente','2025-10-29 07:51:37',NULL),(30,'sapo','marquez','48176177','sapo2@gmail.com','$2y$10$6Zi9.cr6DXaTHCWGc0pIpu9tiMv1ZoiZZAgUVwg1sQZORkZWBumc2','medico','2025-10-29 23:01:13',NULL),(31,'sapo','correa','1245678','sapo4@gmail.com','$2y$10$ex65seyiTUEUDCYzUP9SGuuz9R7Aq9cBT0SKuI6qvJx7QvNU2.oQy','secretaria','2025-10-29 23:02:08',NULL),(32,'adasd','vidal','1243225452','tu@gmail.com','$2y$12$XXMGepYWlR9C.5QoohpJ4OtshuUVVSrSsoFI0DU8ithKabDJeTNsG','paciente','2025-10-29 23:31:04',NULL),(44,'maxia','González','45678910','secretario@gmail.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','secretaria','2025-11-10 03:22:29',NULL);
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary view structure for view `vista_historial_completo`
--

DROP TABLE IF EXISTS `vista_historial_completo`;
/*!50001 DROP VIEW IF EXISTS `vista_historial_completo`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_historial_completo` AS SELECT 
 1 AS `Id_paciente`,
 1 AS `Paciente`,
 1 AS `DNI`,
 1 AS `Fecha_Turno`,
 1 AS `Fecha_Formatted`,
 1 AS `Medico`,
 1 AS `Especialidad`,
 1 AS `Diagnostico`,
 1 AS `Sintomas`,
 1 AS `Observaciones`,
 1 AS `Medicamentos`,
 1 AS `Duracion_Tratamiento`,
 1 AS `Vencimiento_Receta`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vista_historial_pacientes`
--

DROP TABLE IF EXISTS `vista_historial_pacientes`;
/*!50001 DROP VIEW IF EXISTS `vista_historial_pacientes`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_historial_pacientes` AS SELECT 
 1 AS `Paciente`,
 1 AS `DNI`,
 1 AS `Fecha_Turno`,
 1 AS `Medico`,
 1 AS `Especialidad`,
 1 AS `Diagnostico`,
 1 AS `Sintomas`,
 1 AS `Observaciones`,
 1 AS `Medicamentos`,
 1 AS `Duracion_Tratamiento`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vista_recetas_activas`
--

DROP TABLE IF EXISTS `vista_recetas_activas`;
/*!50001 DROP VIEW IF EXISTS `vista_recetas_activas`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_recetas_activas` AS SELECT 
 1 AS `Id_receta`,
 1 AS `Paciente`,
 1 AS `Paciente_DNI`,
 1 AS `Medico`,
 1 AS `Medicamentos`,
 1 AS `Duracion_Tratamiento`,
 1 AS `Fecha_Receta_Formatted`,
 1 AS `Fecha_Vencimiento_Formatted`,
 1 AS `Dias_Restantes`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vista_stats_medicos`
--

DROP TABLE IF EXISTS `vista_stats_medicos`;
/*!50001 DROP VIEW IF EXISTS `vista_stats_medicos`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_stats_medicos` AS SELECT 
 1 AS `Id_medico`,
 1 AS `Medico`,
 1 AS `Especialidad`,
 1 AS `Total_Turnos`,
 1 AS `Turnos_Atendidos`,
 1 AS `Turnos_Pendientes`,
 1 AS `Turnos_Cancelados`,
 1 AS `Total_Diagnosticos`,
 1 AS `Total_Recetas`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vista_turnos_completos`
--

DROP TABLE IF EXISTS `vista_turnos_completos`;
/*!50001 DROP VIEW IF EXISTS `vista_turnos_completos`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_turnos_completos` AS SELECT 
 1 AS `Id_turno`,
 1 AS `Fecha`,
 1 AS `Estado`,
 1 AS `Atendido`,
 1 AS `Fecha_Atencion`,
 1 AS `Paciente`,
 1 AS `Paciente_DNI`,
 1 AS `Obra_Social`,
 1 AS `Libreta_sanitaria`,
 1 AS `Medico`,
 1 AS `Especialidad`,
 1 AS `Diagnostico`,
 1 AS `Fecha_Diagnostico`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vista_turnos_pendientes`
--

DROP TABLE IF EXISTS `vista_turnos_pendientes`;
/*!50001 DROP VIEW IF EXISTS `vista_turnos_pendientes`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vista_turnos_pendientes` AS SELECT 
 1 AS `Id_medico`,
 1 AS `Medico`,
 1 AS `Especialidad`,
 1 AS `Id_turno`,
 1 AS `Fecha`,
 1 AS `Fecha_Formatted`,
 1 AS `Paciente`,
 1 AS `Paciente_DNI`,
 1 AS `Obra_Social`,
 1 AS `Horas_Hasta_Turno`*/;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'turnos_medicos'
--
/*!50003 DROP FUNCTION IF EXISTS `fn_calcular_edad` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_calcular_edad`(p_fecha_nacimiento DATE) RETURNS int
    DETERMINISTIC
BEGIN
    RETURN TIMESTAMPDIFF(YEAR, p_fecha_nacimiento, CURDATE());
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_horario_disponible` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_horario_disponible`(
    p_medico_id INT,
    p_fecha_hora DATETIME
) RETURNS tinyint(1)
    READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE dia_semana VARCHAR(20);
    DECLARE hora_turno TIME;
    DECLARE es_disponible BOOLEAN DEFAULT FALSE;
    DECLARE turnos_ocupados INT DEFAULT 0;
    
    -- Obtener día de la semana
    SET dia_semana = CASE DAYOFWEEK(p_fecha_hora)
        WHEN 1 THEN 'domingo'
        WHEN 2 THEN 'lunes'
        WHEN 3 THEN 'martes'
        WHEN 4 THEN 'miercoles'
        WHEN 5 THEN 'jueves'
        WHEN 6 THEN 'viernes'
        WHEN 7 THEN 'sabado'
    END;
    
    SET hora_turno = TIME(p_fecha_hora);
    
    -- Verificar si el médico atiende ese día y horario
    SELECT COUNT(*) INTO es_disponible
    FROM horario_medico
    WHERE Id_medico = p_medico_id
    AND Dia_semana = dia_semana
    AND hora_turno >= Hora_inicio
    AND hora_turno < Hora_fin;
    
    IF es_disponible = 0 THEN
        RETURN FALSE;
    END IF;
    
    -- Verificar si el horario está ocupado
    SELECT COUNT(*) INTO turnos_ocupados
    FROM turno
    WHERE Id_medico = p_medico_id
    AND Fecha = p_fecha_hora
    AND (Estado IS NULL OR Estado != 'cancelado');
    
    RETURN turnos_ocupados = 0;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_historial_paciente` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_historial_paciente`(IN p_paciente_id INT)
BEGIN
    SELECT 
        t.Fecha AS Fecha_Consulta,
        CONCAT(um.Apellido, ', ', um.Nombre) AS Medico,
        e.Nombre AS Especialidad,
        d.Diagnostico,
        d.Sintomas,
        d.Observaciones,
        r.Medicamentos,
        r.Duracion_Tratamiento,
        r.Fecha_Vencimiento
    FROM turno t
    JOIN medico m ON m.Id_medico = t.Id_medico
    JOIN usuario um ON um.Id_usuario = m.Id_usuario
    JOIN especialidad e ON e.Id_Especialidad = m.Id_Especialidad
    LEFT JOIN diagnostico d ON d.Id_turno = t.Id_turno
    LEFT JOIN receta r ON r.Id_diagnostico = d.Id_diagnostico
    WHERE t.Id_paciente = p_paciente_id
    AND t.Atendido = 1
    ORDER BY t.Fecha DESC;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_medicamentos_mas_recetados` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_medicamentos_mas_recetados`(
    IN p_fecha_desde DATE,
    IN p_fecha_hasta DATE,
    IN p_limit INT
)
BEGIN
    SELECT 
        m.Nombre AS Medicamento,
        m.Principio_Activo,
        m.Presentacion,
        m.Dosis_Usual,
        COUNT(*) AS Veces_Recetado,
        COUNT(DISTINCT r.Id_medico) AS Medicos_Que_Lo_Recetan,
        COUNT(DISTINCT r.Id_paciente) AS Pacientes_Distintos
    FROM medicamento m
    JOIN receta r ON r.Medicamentos LIKE CONCAT('%', m.Nombre, '%')
    WHERE r.Fecha_Receta BETWEEN p_fecha_desde AND p_fecha_hasta
    GROUP BY m.Id_medicamento, m.Nombre, m.Principio_Activo, m.Presentacion, m.Dosis_Usual
    ORDER BY Veces_Recetado DESC
    LIMIT p_limit;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_proximos_turnos_paciente` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_proximos_turnos_paciente`(IN p_paciente_id INT)
BEGIN
    SELECT 
        t.Id_turno,
        t.Fecha,
        DATE_FORMAT(t.Fecha, '%d/%m/%Y %h:%i %p') AS Fecha_Formatted,
        CONCAT(um.Apellido, ', ', um.Nombre) AS Medico,
        e.Nombre AS Especialidad,
        IFNULL(t.Estado, 'reservado') AS Estado,
        t.Atendido,
        DATEDIFF(DATE(t.Fecha), CURDATE()) AS Dias_Hasta_Turno,
        CASE 
            WHEN TIMESTAMPDIFF(HOUR, NOW(), t.Fecha) >= 24 THEN 1
            ELSE 0
        END AS Puede_Cancelar,
        CASE 
            WHEN TIMESTAMPDIFF(HOUR, NOW(), t.Fecha) >= 24 THEN 'Sí'
            ELSE CONCAT('No (debe ser con ', CEIL(24 - TIMESTAMPDIFF(HOUR, NOW(), t.Fecha)), ' horas de anticipación)')
        END AS Info_Cancelacion
    FROM turno t
    JOIN medico m ON m.Id_medico = t.Id_medico
    JOIN usuario um ON um.Id_usuario = m.Id_usuario
    JOIN especialidad e ON e.Id_Especialidad = m.Id_Especialidad
    WHERE t.Id_paciente = p_paciente_id
    AND t.Fecha >= NOW()
    AND (t.Estado IS NULL OR t.Estado != 'cancelado')
    ORDER BY t.Fecha ASC;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_reporte_diario` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reporte_diario`(IN p_fecha DATE)
BEGIN
    SELECT 
        DATE_FORMAT(p_fecha, '%d/%m/%Y') AS Fecha,
        CONCAT(um.Apellido, ', ', um.Nombre) AS Medico,
        e.Nombre AS Especialidad,
        COUNT(*) AS Total_Turnos,
        SUM(CASE WHEN t.Atendido = 1 THEN 1 ELSE 0 END) AS Atendidos,
        SUM(CASE WHEN t.Atendido = 0 AND (t.Estado IS NULL OR t.Estado != 'cancelado') THEN 1 ELSE 0 END) AS Pendientes,
        SUM(CASE WHEN t.Estado = 'cancelado' THEN 1 ELSE 0 END) AS Cancelados,
        GROUP_CONCAT(
            CONCAT(TIME_FORMAT(TIME(t.Fecha), '%h:%i %p'), ' - ', up.Apellido)
            ORDER BY t.Fecha
            SEPARATOR ' | '
        ) AS Detalle_Horarios
    FROM turno t
    JOIN medico m ON m.Id_medico = t.Id_medico
    JOIN usuario um ON um.Id_usuario = m.Id_usuario
    JOIN especialidad e ON e.Id_Especialidad = m.Id_Especialidad
    JOIN paciente p ON p.Id_paciente = t.Id_paciente
    JOIN usuario up ON up.Id_usuario = p.Id_usuario
    WHERE DATE(t.Fecha) = p_fecha
    GROUP BY m.Id_medico, um.Apellido, um.Nombre, e.Nombre
    ORDER BY Total_Turnos DESC;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_stats_por_especialidad` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_stats_por_especialidad`()
BEGIN
    SELECT 
        e.Nombre AS Especialidad,
        COUNT(DISTINCT m.Id_medico) AS Total_Medicos,
        COUNT(DISTINCT t.Id_turno) AS Total_Turnos,
        SUM(CASE WHEN t.Atendido = 1 THEN 1 ELSE 0 END) AS Turnos_Atendidos,
        SUM(CASE WHEN t.Estado = 'cancelado' THEN 1 ELSE 0 END) AS Turnos_Cancelados,
        SUM(CASE WHEN t.Atendido = 0 AND (t.Estado IS NULL OR t.Estado != 'cancelado') THEN 1 ELSE 0 END) AS Turnos_Pendientes,
        ROUND(
            CASE 
                WHEN COUNT(DISTINCT t.Id_turno) > 0 
                THEN (SUM(CASE WHEN t.Atendido = 1 THEN 1 ELSE 0 END) * 100.0) / COUNT(DISTINCT t.Id_turno)
                ELSE 0 
            END, 
        2) AS Porcentaje_Atencion
    FROM especialidad e
    LEFT JOIN medico m ON m.Id_Especialidad = e.Id_Especialidad AND m.Activo = 1
    LEFT JOIN turno t ON t.Id_medico = m.Id_medico
    WHERE e.Activo = 1
    GROUP BY e.Id_Especialidad, e.Nombre
    ORDER BY Total_Turnos DESC;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_stats_por_fecha` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_stats_por_fecha`(
    IN p_fecha_desde DATE,
    IN p_fecha_hasta DATE
)
BEGIN
    SELECT 
        DATE(t.Fecha) AS Fecha,
        COUNT(*) AS Total_Turnos,
        SUM(CASE WHEN t.Atendido = 1 THEN 1 ELSE 0 END) AS Atendidos,
        SUM(CASE WHEN t.Estado = 'cancelado' THEN 1 ELSE 0 END) AS Cancelados,
        SUM(CASE WHEN t.Atendido = 0 AND t.Estado != 'cancelado' THEN 1 ELSE 0 END) AS Pendientes
    FROM turno t
    WHERE DATE(t.Fecha) BETWEEN p_fecha_desde AND p_fecha_hasta
    GROUP BY DATE(t.Fecha)
    ORDER BY Fecha DESC;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_turnos_hoy_medico` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_turnos_hoy_medico`(IN p_medico_id INT)
BEGIN
    SELECT 
        t.Id_turno,
        t.Fecha,
        TIME_FORMAT(TIME(t.Fecha), '%h:%i %p') AS Hora_Formatted,
        t.Atendido,
        CONCAT(up.Apellido, ', ', up.Nombre) AS Paciente,
        up.dni AS Paciente_DNI,
        IFNULL(os.Nombre, 'Sin obra social') AS Obra_Social,
        p.Libreta_sanitaria,
        TIMESTAMPDIFF(MINUTE, NOW(), t.Fecha) AS Minutos_Hasta_Turno
    FROM turno t
    JOIN paciente p ON p.Id_paciente = t.Id_paciente
    JOIN usuario up ON up.Id_usuario = p.Id_usuario
    LEFT JOIN obra_social os ON os.Id_obra_social = p.Id_obra_social
    WHERE t.Id_medico = p_medico_id
    AND DATE(t.Fecha) = CURDATE()
    AND (t.Estado IS NULL OR t.Estado != 'cancelado')
    ORDER BY t.Fecha ASC;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `vista_historial_completo`
--

/*!50001 DROP VIEW IF EXISTS `vista_historial_completo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_historial_completo` AS select `p`.`Id_paciente` AS `Id_paciente`,concat(`u`.`Apellido`,', ',`u`.`Nombre`) AS `Paciente`,`u`.`dni` AS `DNI`,`t`.`Fecha` AS `Fecha_Turno`,date_format(`t`.`Fecha`,'%d/%m/%Y %h:%i %p') AS `Fecha_Formatted`,concat(`um`.`Apellido`,', ',`um`.`Nombre`) AS `Medico`,`e`.`Nombre` AS `Especialidad`,`d`.`Diagnostico` AS `Diagnostico`,`d`.`Sintomas` AS `Sintomas`,`d`.`Observaciones` AS `Observaciones`,`r`.`Medicamentos` AS `Medicamentos`,`r`.`Duracion_Tratamiento` AS `Duracion_Tratamiento`,date_format(`r`.`Fecha_Vencimiento`,'%d/%m/%Y') AS `Vencimiento_Receta` from (((((((`paciente` `p` join `usuario` `u` on((`u`.`Id_usuario` = `p`.`Id_usuario`))) left join `turno` `t` on((`t`.`Id_paciente` = `p`.`Id_paciente`))) left join `diagnostico` `d` on((`d`.`Id_turno` = `t`.`Id_turno`))) left join `receta` `r` on((`r`.`Id_diagnostico` = `d`.`Id_diagnostico`))) left join `medico` `m` on((`m`.`Id_medico` = `t`.`Id_medico`))) left join `usuario` `um` on((`um`.`Id_usuario` = `m`.`Id_usuario`))) left join `especialidad` `e` on((`e`.`Id_Especialidad` = `m`.`Id_Especialidad`))) where (`t`.`Atendido` = 1) order by `t`.`Fecha` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vista_historial_pacientes`
--

/*!50001 DROP VIEW IF EXISTS `vista_historial_pacientes`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_historial_pacientes` AS select concat(`u`.`Apellido`,', ',`u`.`Nombre`) AS `Paciente`,`u`.`dni` AS `DNI`,`t`.`Fecha` AS `Fecha_Turno`,concat(`um`.`Apellido`,', ',`um`.`Nombre`) AS `Medico`,`e`.`Nombre` AS `Especialidad`,`d`.`Diagnostico` AS `Diagnostico`,`d`.`Sintomas` AS `Sintomas`,`d`.`Observaciones` AS `Observaciones`,`r`.`Medicamentos` AS `Medicamentos`,`r`.`Duracion_Tratamiento` AS `Duracion_Tratamiento` from (((((((`paciente` `p` join `usuario` `u` on((`u`.`Id_usuario` = `p`.`Id_usuario`))) left join `turno` `t` on((`t`.`Id_paciente` = `p`.`Id_paciente`))) left join `diagnostico` `d` on((`d`.`Id_turno` = `t`.`Id_turno`))) left join `receta` `r` on((`r`.`Id_diagnostico` = `d`.`Id_diagnostico`))) left join `medico` `m` on((`m`.`Id_medico` = `t`.`Id_medico`))) left join `usuario` `um` on((`um`.`Id_usuario` = `m`.`Id_usuario`))) left join `especialidad` `e` on((`e`.`Id_Especialidad` = `m`.`Id_Especialidad`))) where (`t`.`Atendido` = 1) order by `t`.`Fecha` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vista_recetas_activas`
--

/*!50001 DROP VIEW IF EXISTS `vista_recetas_activas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_recetas_activas` AS select `r`.`Id_receta` AS `Id_receta`,concat(`up`.`Apellido`,', ',`up`.`Nombre`) AS `Paciente`,`up`.`dni` AS `Paciente_DNI`,concat(`um`.`Apellido`,', ',`um`.`Nombre`) AS `Medico`,`r`.`Medicamentos` AS `Medicamentos`,`r`.`Duracion_Tratamiento` AS `Duracion_Tratamiento`,date_format(`r`.`Fecha_Receta`,'%d/%m/%Y') AS `Fecha_Receta_Formatted`,date_format(`r`.`Fecha_Vencimiento`,'%d/%m/%Y') AS `Fecha_Vencimiento_Formatted`,(to_days(`r`.`Fecha_Vencimiento`) - to_days(curdate())) AS `Dias_Restantes` from ((((`receta` `r` join `paciente` `p` on((`p`.`Id_paciente` = `r`.`Id_paciente`))) join `usuario` `up` on((`up`.`Id_usuario` = `p`.`Id_usuario`))) join `medico` `m` on((`m`.`Id_medico` = `r`.`Id_medico`))) join `usuario` `um` on((`um`.`Id_usuario` = `m`.`Id_usuario`))) where (`r`.`Fecha_Vencimiento` >= curdate()) order by `r`.`Fecha_Vencimiento` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vista_stats_medicos`
--

/*!50001 DROP VIEW IF EXISTS `vista_stats_medicos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_stats_medicos` AS select `m`.`Id_medico` AS `Id_medico`,concat(`u`.`Apellido`,', ',`u`.`Nombre`) AS `Medico`,`e`.`Nombre` AS `Especialidad`,count(distinct `t`.`Id_turno`) AS `Total_Turnos`,sum((case when (`t`.`Atendido` = 1) then 1 else 0 end)) AS `Turnos_Atendidos`,sum((case when ((`t`.`Atendido` = 0) and ((`t`.`Estado` is null) or (`t`.`Estado` <> 'cancelado'))) then 1 else 0 end)) AS `Turnos_Pendientes`,sum((case when (`t`.`Estado` = 'cancelado') then 1 else 0 end)) AS `Turnos_Cancelados`,count(distinct `d`.`Id_diagnostico`) AS `Total_Diagnosticos`,count(distinct `r`.`Id_receta`) AS `Total_Recetas` from (((((`medico` `m` join `usuario` `u` on((`u`.`Id_usuario` = `m`.`Id_usuario`))) join `especialidad` `e` on((`e`.`Id_Especialidad` = `m`.`Id_Especialidad`))) left join `turno` `t` on((`t`.`Id_medico` = `m`.`Id_medico`))) left join `diagnostico` `d` on((`d`.`Id_medico` = `m`.`Id_medico`))) left join `receta` `r` on((`r`.`Id_medico` = `m`.`Id_medico`))) where (`m`.`Activo` = 1) group by `m`.`Id_medico`,`u`.`Apellido`,`u`.`Nombre`,`e`.`Nombre` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vista_turnos_completos`
--

/*!50001 DROP VIEW IF EXISTS `vista_turnos_completos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_turnos_completos` AS select `t`.`Id_turno` AS `Id_turno`,`t`.`Fecha` AS `Fecha`,ifnull(`t`.`Estado`,'reservado') AS `Estado`,`t`.`Atendido` AS `Atendido`,`t`.`Fecha_Atencion` AS `Fecha_Atencion`,concat(`up`.`Apellido`,', ',`up`.`Nombre`) AS `Paciente`,`up`.`dni` AS `Paciente_DNI`,ifnull(`os`.`Nombre`,'Sin obra social') AS `Obra_Social`,ifnull(`p`.`Libreta_sanitaria`,'N/A') AS `Libreta_sanitaria`,concat(`um`.`Apellido`,', ',`um`.`Nombre`) AS `Medico`,`e`.`Nombre` AS `Especialidad`,`d`.`Diagnostico` AS `Diagnostico`,`d`.`Fecha_Diagnostico` AS `Fecha_Diagnostico` from (((((((`turno` `t` join `paciente` `p` on((`p`.`Id_paciente` = `t`.`Id_paciente`))) join `usuario` `up` on((`up`.`Id_usuario` = `p`.`Id_usuario`))) left join `obra_social` `os` on((`os`.`Id_obra_social` = `p`.`Id_obra_social`))) join `medico` `m` on((`m`.`Id_medico` = `t`.`Id_medico`))) join `usuario` `um` on((`um`.`Id_usuario` = `m`.`Id_usuario`))) join `especialidad` `e` on((`e`.`Id_Especialidad` = `m`.`Id_Especialidad`))) left join `diagnostico` `d` on((`d`.`Id_turno` = `t`.`Id_turno`))) order by `t`.`Fecha` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vista_turnos_pendientes`
--

/*!50001 DROP VIEW IF EXISTS `vista_turnos_pendientes`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vista_turnos_pendientes` AS select `m`.`Id_medico` AS `Id_medico`,concat(`um`.`Apellido`,', ',`um`.`Nombre`) AS `Medico`,`e`.`Nombre` AS `Especialidad`,`t`.`Id_turno` AS `Id_turno`,`t`.`Fecha` AS `Fecha`,date_format(`t`.`Fecha`,'%d/%m/%Y %h:%i %p') AS `Fecha_Formatted`,concat(`up`.`Apellido`,', ',`up`.`Nombre`) AS `Paciente`,`up`.`dni` AS `Paciente_DNI`,ifnull(`os`.`Nombre`,'Sin obra social') AS `Obra_Social`,timestampdiff(HOUR,now(),`t`.`Fecha`) AS `Horas_Hasta_Turno` from ((((((`turno` `t` join `medico` `m` on((`m`.`Id_medico` = `t`.`Id_medico`))) join `usuario` `um` on((`um`.`Id_usuario` = `m`.`Id_usuario`))) join `especialidad` `e` on((`e`.`Id_Especialidad` = `m`.`Id_Especialidad`))) join `paciente` `p` on((`p`.`Id_paciente` = `t`.`Id_paciente`))) join `usuario` `up` on((`up`.`Id_usuario` = `p`.`Id_usuario`))) left join `obra_social` `os` on((`os`.`Id_obra_social` = `p`.`Id_obra_social`))) where ((`t`.`Atendido` = 0) and ((`t`.`Estado` is null) or (`t`.`Estado` <> 'cancelado')) and (`t`.`Fecha` >= now())) order by `t`.`Fecha` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-10  0:25:19
