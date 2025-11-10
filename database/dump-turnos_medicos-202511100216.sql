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
  `Diagnostico` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Diagnóstico médico principal',
  `Observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Observaciones y notas adicionales',
  `Sintomas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Síntomas reportados por el paciente',
  `Fecha_Diagnostico` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_diagnostico`),
  KEY `idx_turno` (`Id_turno`),
  KEY `idx_medico` (`Id_medico`),
  KEY `idx_fecha` (`Fecha_Diagnostico`),
  CONSTRAINT `fk_diagnostico_medico` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT,
  CONSTRAINT `fk_diagnostico_turno` FOREIGN KEY (`Id_turno`) REFERENCES `turno` (`Id_turno`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `Tipo_Registro` enum('consulta','diagnostico','receta','nota','estudio') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'nota',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horario_medico`
--

LOCK TABLES `horario_medico` WRITE;
/*!40000 ALTER TABLE `horario_medico` DISABLE KEYS */;
INSERT INTO `horario_medico` VALUES (1,1,'lunes','08:00:00','12:00:00'),(2,1,'lunes','14:00:00','18:00:00'),(3,1,'miercoles','08:00:00','12:00:00'),(4,1,'viernes','14:00:00','18:00:00'),(5,2,'lunes','09:00:00','13:00:00'),(6,2,'martes','09:00:00','13:00:00'),(7,2,'jueves','09:00:00','13:00:00'),(8,2,'viernes','09:00:00','13:00:00'),(9,3,'lunes','08:00:00','14:00:00'),(10,3,'martes','08:00:00','14:00:00'),(11,3,'miercoles','08:00:00','14:00:00'),(12,3,'jueves','08:00:00','14:00:00'),(13,3,'viernes','08:00:00','14:00:00'),(14,4,'martes','14:00:00','19:00:00'),(15,4,'jueves','14:00:00','19:00:00');
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medico`
--

LOCK TABLES `medico` WRITE;
/*!40000 ALTER TABLE `medico` DISABLE KEYS */;
INSERT INTO `medico` VALUES (1,'MED-001',3,3,30,1),(2,'MED-002',4,2,30,1),(3,'MED-003',5,1,30,1),(4,'MED-004',6,5,30,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paciente`
--

LOCK TABLES `paciente` WRITE;
/*!40000 ALTER TABLE `paciente` DISABLE KEYS */;
INSERT INTO `paciente` VALUES (1,6,'12345','LIB-001',7,1,'2025-11-10 05:12:16'),(2,1,'67890','LIB-002',8,1,'2025-11-10 05:12:16'),(3,9,NULL,'LIB-003',9,1,'2025-11-10 05:12:16');
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
  `Medicamentos` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Lista de medicamentos recetados',
  `Indicaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `Duracion_Tratamiento` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Fecha_Receta` datetime DEFAULT CURRENT_TIMESTAMP,
  `Fecha_Vencimiento` date DEFAULT NULL,
  PRIMARY KEY (`Id_receta`),
  KEY `idx_diagnostico` (`Id_diagnostico`),
  KEY `idx_medico` (`Id_medico`),
  KEY `idx_paciente` (`Id_paciente`),
  KEY `idx_fecha` (`Fecha_Receta`),
  CONSTRAINT `fk_receta_diagnostico` FOREIGN KEY (`Id_diagnostico`) REFERENCES `diagnostico` (`Id_diagnostico`) ON DELETE CASCADE,
  CONSTRAINT `fk_receta_medico` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT,
  CONSTRAINT `fk_receta_paciente` FOREIGN KEY (`Id_paciente`) REFERENCES `paciente` (`Id_paciente`) ON DELETE RESTRICT,
  CONSTRAINT `chk_fecha_vencimiento` CHECK (((`Fecha_Vencimiento` is null) or (`Fecha_Vencimiento` >= cast(`Fecha_Receta` as date))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `secretaria`
--

LOCK TABLES `secretaria` WRITE;
/*!40000 ALTER TABLE `secretaria` DISABLE KEYS */;
INSERT INTO `secretaria` VALUES (1,1,1,'2025-11-10 05:12:16'),(2,2,1,'2025-11-10 05:12:16');
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
  KEY `fk_turno_medico_atencion` (`Id_medico_atencion`),
  KEY `idx_turno_atendido` (`Atendido`,`Fecha`),
  KEY `idx_fecha_atencion` (`Fecha_Atencion`),
  CONSTRAINT `fk_turno_medico_atencion` FOREIGN KEY (`Id_medico_atencion`) REFERENCES `medico` (`Id_medico`) ON DELETE SET NULL,
  CONSTRAINT `turno_medico_FK` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT,
  CONSTRAINT `turno_paciente_FK` FOREIGN KEY (`Id_paciente`) REFERENCES `paciente` (`Id_paciente`) ON DELETE RESTRICT,
  CONSTRAINT `turno_secretaria_FK` FOREIGN KEY (`Id_secretaria`) REFERENCES `secretaria` (`Id_secretaria`) ON DELETE SET NULL,
  CONSTRAINT `chk_atendido` CHECK ((`Atendido` in (0,1))),
  CONSTRAINT `chk_fecha_atencion` CHECK (((`Fecha_Atencion` is null) or (`Fecha_Atencion` >= `Fecha`)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `turno`
--

LOCK TABLES `turno` WRITE;
/*!40000 ALTER TABLE `turno` DISABLE KEYS */;
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
    IF NEW.Atendido = 1 AND OLD.Atendido = 0 THEN
        IF EXISTS (SELECT 1 FROM diagnostico WHERE Id_turno = NEW.Id_turno) THEN
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
  `Contraseña` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Rol` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `Fecha_Registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acceso` datetime DEFAULT NULL,
  PRIMARY KEY (`Id_usuario`),
  UNIQUE KEY `email_UNIQUE` (`email`),
  UNIQUE KEY `dni_UNIQUE` (`dni`),
  KEY `idx_rol` (`Rol`),
  KEY `idx_email` (`email`),
  KEY `idx_usuario_search` (`dni`,`email`,`Nombre`,`Apellido`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,'María','González','38765432','maria.gonzalez@clinica.com','$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGmXn42LX8MkVbQrAu','secretaria','2025-11-10 05:12:16',NULL),(2,'Laura','Fernández','40123456','laura.fernandez@clinica.com','$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGmXn42LX8MkVbQrAu','secretaria','2025-11-10 05:12:16',NULL),(3,'Carlos','Ramírez','30123456','carlos.ramirez@clinica.com','$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGmXn42LX8MkVbQrAu','medico','2025-11-10 05:12:16',NULL),(4,'Ana','Martínez','31234567','ana.martinez@clinica.com','$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGmXn42LX8MkVbQrAu','medico','2025-11-10 05:12:16',NULL),(5,'Roberto','Silva','32345678','roberto.silva@clinica.com','$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGmXn42LX8MkVbQrAu','medico','2025-11-10 05:12:16',NULL),(6,'Patricia','López','33456789','patricia.lopez@clinica.com','$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGmXn42LX8MkVbQrAu','medico','2025-11-10 05:12:16',NULL),(7,'Juan','Pérez','40111222','juan.perez@email.com','$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGmXn42LX8MkVbQrAu','paciente','2025-11-10 05:12:16',NULL),(8,'María','Rodríguez','40222333','maria.rodriguez@email.com','$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGmXn42LX8MkVbQrAu','paciente','2025-11-10 05:12:16',NULL),(9,'Pedro','Gómez','40333444','pedro.gomez@email.com','$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGmXn42LX8MkVbQrAu','paciente','2025-11-10 05:12:16',NULL);
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
    DECLARE es_disponible INT DEFAULT 0;
    DECLARE turnos_ocupados INT DEFAULT 0;
    
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
    
    SELECT COUNT(*) INTO es_disponible
    FROM horario_medico
    WHERE Id_medico = p_medico_id
    AND Dia_semana = dia_semana
    AND hora_turno >= Hora_inicio
    AND hora_turno < Hora_fin;
    
    IF es_disponible = 0 THEN
        RETURN FALSE;
    END IF;
    
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
        END AS Puede_Cancelar
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
        SUM(CASE WHEN t.Estado = 'cancelado' THEN 1 ELSE 0 END) AS Turnos_Cancelados
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
        p.Libreta_sanitaria
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

-- Dump completed on 2025-11-10  2:16:57
