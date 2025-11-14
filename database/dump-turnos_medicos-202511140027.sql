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
  `Id_diagnostico` bigint unsigned NOT NULL AUTO_INCREMENT,
  `Id_turno` bigint unsigned NOT NULL,
  `Id_medico` bigint unsigned NOT NULL,
  `Diagnostico` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `Sintomas` text COLLATE utf8mb4_unicode_ci,
  `Observaciones` text COLLATE utf8mb4_unicode_ci,
  `Fecha_Diagnostico` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_diagnostico`),
  UNIQUE KEY `Id_diagnostico` (`Id_diagnostico`),
  KEY `idx_turno` (`Id_turno`),
  KEY `idx_medico` (`Id_medico`),
  KEY `idx_fecha` (`Fecha_Diagnostico`),
  CONSTRAINT `diagnostico_ibfk_1` FOREIGN KEY (`Id_turno`) REFERENCES `turno` (`Id_turno`) ON DELETE CASCADE,
  CONSTRAINT `diagnostico_ibfk_2` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT
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
  `Id_Especialidad` bigint unsigned NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Descripcion` text COLLATE utf8mb4_unicode_ci,
  `Activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`Id_Especialidad`),
  UNIQUE KEY `Id_Especialidad` (`Id_Especialidad`),
  UNIQUE KEY `Nombre` (`Nombre`),
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
  `Id_historial` bigint unsigned NOT NULL AUTO_INCREMENT,
  `Id_paciente` bigint unsigned NOT NULL,
  `Id_turno` bigint unsigned DEFAULT NULL,
  `Id_medico` bigint unsigned NOT NULL,
  `Tipo_Registro` enum('consulta','diagnostico','receta','nota','estudio') COLLATE utf8mb4_unicode_ci DEFAULT 'nota',
  `Contenido` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `Fecha_Registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_historial`),
  UNIQUE KEY `Id_historial` (`Id_historial`),
  KEY `Id_turno` (`Id_turno`),
  KEY `Id_medico` (`Id_medico`),
  KEY `idx_paciente` (`Id_paciente`),
  KEY `idx_fecha` (`Fecha_Registro`),
  KEY `idx_tipo` (`Tipo_Registro`),
  CONSTRAINT `historial_clinico_ibfk_1` FOREIGN KEY (`Id_paciente`) REFERENCES `paciente` (`Id_paciente`) ON DELETE CASCADE,
  CONSTRAINT `historial_clinico_ibfk_2` FOREIGN KEY (`Id_turno`) REFERENCES `turno` (`Id_turno`) ON DELETE SET NULL,
  CONSTRAINT `historial_clinico_ibfk_3` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT
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
  `Id_horario` bigint unsigned NOT NULL AUTO_INCREMENT,
  `Id_medico` bigint unsigned NOT NULL,
  `Dia_semana` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Hora_inicio` time NOT NULL,
  `Hora_fin` time NOT NULL,
  PRIMARY KEY (`Id_horario`),
  UNIQUE KEY `Id_horario` (`Id_horario`),
  UNIQUE KEY `unique_horario` (`Id_medico`,`Dia_semana`,`Hora_inicio`,`Hora_fin`),
  KEY `idx_medico_dia` (`Id_medico`,`Dia_semana`),
  KEY `idx_horario` (`Hora_inicio`,`Hora_fin`),
  CONSTRAINT `horario_medico_ibfk_1` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE CASCADE,
  CONSTRAINT `horario_medico_chk_1` CHECK ((`Dia_semana` in (_utf8mb4'lunes',_utf8mb4'martes',_utf8mb4'miercoles',_utf8mb4'jueves',_utf8mb4'viernes',_utf8mb4'sabado',_utf8mb4'domingo')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horario_medico`
--

LOCK TABLES `horario_medico` WRITE;
/*!40000 ALTER TABLE `horario_medico` DISABLE KEYS */;
/*!40000 ALTER TABLE `horario_medico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicamento`
--

DROP TABLE IF EXISTS `medicamento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medicamento` (
  `Id_medicamento` bigint unsigned NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Principio_Activo` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Presentacion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Dosis_Usual` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Activo` tinyint(1) DEFAULT '1',
  `Fecha_Alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_medicamento`),
  UNIQUE KEY `Id_medicamento` (`Id_medicamento`),
  KEY `idx_nombre` (`Nombre`),
  KEY `idx_activo` (`Activo`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicamento`
--

LOCK TABLES `medicamento` WRITE;
/*!40000 ALTER TABLE `medicamento` DISABLE KEYS */;
INSERT INTO `medicamento` VALUES (1,'Ibuprofeno 400mg','Ibuprofeno','Comprimidos x 30','1 comp. cada 8hs',1,'2025-11-14 03:18:32'),(2,'Paracetamol 500mg','Paracetamol','Comprimidos x 30','1 comp. cada 6-8hs',1,'2025-11-14 03:18:32'),(3,'Amoxicilina 500mg','Amoxicilina','Cápsulas x 21','1 cáps. cada 8hs',1,'2025-11-14 03:18:32'),(4,'Omeprazol 20mg','Omeprazol','Cápsulas x 28','1 cáps. en ayunas',1,'2025-11-14 03:18:32'),(5,'Losartán 50mg','Losartán','Comprimidos x 30','1 comp. por día',1,'2025-11-14 03:18:32'),(6,'Enalapril 10mg','Enalapril','Comprimidos x 30','1 comp. por día',1,'2025-11-14 03:18:32'),(7,'Metformina 850mg','Metformina','Comprimidos x 60','1 comp. cada 12hs',1,'2025-11-14 03:18:32'),(8,'Atorvastatina 20mg','Atorvastatina','Comprimidos x 30','1 comp. por noche',1,'2025-11-14 03:18:32'),(9,'Salbutamol 100mcg','Salbutamol','Aerosol 200 dosis','2 puff cada 6-8hs',1,'2025-11-14 03:18:32'),(10,'Loratadina 10mg','Loratadina','Comprimidos x 30','1 comp. por día',1,'2025-11-14 03:18:32');
/*!40000 ALTER TABLE `medicamento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medico`
--

DROP TABLE IF EXISTS `medico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medico` (
  `Id_medico` bigint unsigned NOT NULL AUTO_INCREMENT,
  `Id_usuario` bigint unsigned NOT NULL,
  `Id_Especialidad` bigint unsigned NOT NULL,
  `Legajo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Duracion_Turno` int DEFAULT '30',
  `Activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`Id_medico`),
  UNIQUE KEY `Id_medico` (`Id_medico`),
  UNIQUE KEY `Id_usuario` (`Id_usuario`),
  UNIQUE KEY `Legajo` (`Legajo`),
  KEY `idx_especialidad` (`Id_Especialidad`),
  KEY `idx_activo` (`Activo`),
  CONSTRAINT `medico_ibfk_1` FOREIGN KEY (`Id_usuario`) REFERENCES `usuario` (`Id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `medico_ibfk_2` FOREIGN KEY (`Id_Especialidad`) REFERENCES `especialidad` (`Id_Especialidad`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medico`
--

LOCK TABLES `medico` WRITE;
/*!40000 ALTER TABLE `medico` DISABLE KEYS */;
/*!40000 ALTER TABLE `medico` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `obra_social`
--

DROP TABLE IF EXISTS `obra_social`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `obra_social` (
  `Id_obra_social` bigint unsigned NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Activo` tinyint(1) DEFAULT '1',
  `Fecha_Alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_obra_social`),
  UNIQUE KEY `Id_obra_social` (`Id_obra_social`),
  UNIQUE KEY `Nombre` (`Nombre`),
  KEY `idx_activo` (`Activo`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `obra_social`
--

LOCK TABLES `obra_social` WRITE;
/*!40000 ALTER TABLE `obra_social` DISABLE KEYS */;
INSERT INTO `obra_social` VALUES (1,'OSPROTURA',1,'2025-11-14 03:18:32'),(2,'OSMISS',1,'2025-11-14 03:18:32'),(3,'OSTCARA',1,'2025-11-14 03:18:32'),(4,'OSTAXBA',1,'2025-11-14 03:18:32'),(5,'OSDE',1,'2025-11-14 03:18:32'),(6,'Swiss Medical',1,'2025-11-14 03:18:32'),(7,'Sin obra social',1,'2025-11-14 03:18:32');
/*!40000 ALTER TABLE `obra_social` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paciente`
--

DROP TABLE IF EXISTS `paciente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paciente` (
  `Id_paciente` bigint unsigned NOT NULL AUTO_INCREMENT,
  `Id_usuario` bigint unsigned NOT NULL,
  `Id_obra_social` bigint unsigned DEFAULT NULL,
  `Nro_carnet` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Libreta_sanitaria` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N/A',
  `Activo` tinyint(1) DEFAULT '1',
  `Fecha_Alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_paciente`),
  UNIQUE KEY `Id_paciente` (`Id_paciente`),
  UNIQUE KEY `Id_usuario` (`Id_usuario`),
  KEY `idx_obra_social` (`Id_obra_social`),
  KEY `idx_activo` (`Activo`),
  CONSTRAINT `paciente_ibfk_1` FOREIGN KEY (`Id_usuario`) REFERENCES `usuario` (`Id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `paciente_ibfk_2` FOREIGN KEY (`Id_obra_social`) REFERENCES `obra_social` (`Id_obra_social`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paciente`
--

LOCK TABLES `paciente` WRITE;
/*!40000 ALTER TABLE `paciente` DISABLE KEYS */;
/*!40000 ALTER TABLE `paciente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receta`
--

DROP TABLE IF EXISTS `receta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `receta` (
  `Id_receta` bigint unsigned NOT NULL AUTO_INCREMENT,
  `Id_diagnostico` bigint unsigned NOT NULL,
  `Id_turno` bigint unsigned NOT NULL,
  `Id_medico` bigint unsigned NOT NULL,
  `Id_paciente` bigint unsigned NOT NULL,
  `Medicamentos` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Lista de medicamentos e indicaciones',
  `Indicaciones` text COLLATE utf8mb4_unicode_ci,
  `Duracion_Tratamiento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Fecha_Receta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `Fecha_Vencimiento` date DEFAULT NULL,
  PRIMARY KEY (`Id_receta`),
  UNIQUE KEY `Id_receta` (`Id_receta`),
  KEY `Id_turno` (`Id_turno`),
  KEY `idx_diagnostico` (`Id_diagnostico`),
  KEY `idx_medico` (`Id_medico`),
  KEY `idx_paciente` (`Id_paciente`),
  KEY `idx_fecha_vencimiento` (`Fecha_Vencimiento`),
  CONSTRAINT `receta_ibfk_1` FOREIGN KEY (`Id_diagnostico`) REFERENCES `diagnostico` (`Id_diagnostico`) ON DELETE CASCADE,
  CONSTRAINT `receta_ibfk_2` FOREIGN KEY (`Id_turno`) REFERENCES `turno` (`Id_turno`) ON DELETE CASCADE,
  CONSTRAINT `receta_ibfk_3` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT,
  CONSTRAINT `receta_ibfk_4` FOREIGN KEY (`Id_paciente`) REFERENCES `paciente` (`Id_paciente`) ON DELETE RESTRICT,
  CONSTRAINT `receta_chk_1` CHECK (((`Fecha_Vencimiento` is null) or (`Fecha_Vencimiento` >= cast(`Fecha_Receta` as date))))
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
  `Id_secretaria` bigint unsigned NOT NULL AUTO_INCREMENT,
  `Id_usuario` bigint unsigned NOT NULL,
  `Activo` tinyint(1) DEFAULT '1',
  `Fecha_Alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_secretaria`),
  UNIQUE KEY `Id_secretaria` (`Id_secretaria`),
  UNIQUE KEY `Id_usuario` (`Id_usuario`),
  KEY `idx_activo` (`Activo`),
  CONSTRAINT `secretaria_ibfk_1` FOREIGN KEY (`Id_usuario`) REFERENCES `usuario` (`Id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `secretaria`
--

LOCK TABLES `secretaria` WRITE;
/*!40000 ALTER TABLE `secretaria` DISABLE KEYS */;
/*!40000 ALTER TABLE `secretaria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `turno`
--

DROP TABLE IF EXISTS `turno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `turno` (
  `Id_turno` bigint unsigned NOT NULL AUTO_INCREMENT,
  `Id_paciente` bigint unsigned NOT NULL,
  `Id_medico` bigint unsigned NOT NULL,
  `Id_secretaria` bigint unsigned DEFAULT NULL,
  `Fecha` timestamp NOT NULL,
  `Estado` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'reservado',
  `Atendido` tinyint(1) DEFAULT '0',
  `Fecha_Atencion` timestamp NULL DEFAULT NULL,
  `Id_medico_atencion` bigint unsigned DEFAULT NULL,
  `Observaciones` text COLLATE utf8mb4_unicode_ci,
  `Fecha_Creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `Fecha_Modificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_turno`),
  UNIQUE KEY `Id_turno` (`Id_turno`),
  UNIQUE KEY `unique_turno` (`Id_medico`,`Fecha`),
  KEY `Id_secretaria` (`Id_secretaria`),
  KEY `Id_medico_atencion` (`Id_medico_atencion`),
  KEY `idx_fecha` (`Fecha`),
  KEY `idx_medico_fecha` (`Id_medico`,`Fecha`),
  KEY `idx_paciente_fecha` (`Id_paciente`,`Fecha`),
  KEY `idx_estado` (`Estado`),
  KEY `idx_atendido` (`Atendido`),
  CONSTRAINT `turno_ibfk_1` FOREIGN KEY (`Id_paciente`) REFERENCES `paciente` (`Id_paciente`) ON DELETE RESTRICT,
  CONSTRAINT `turno_ibfk_2` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT,
  CONSTRAINT `turno_ibfk_3` FOREIGN KEY (`Id_secretaria`) REFERENCES `secretaria` (`Id_secretaria`) ON DELETE SET NULL,
  CONSTRAINT `turno_ibfk_4` FOREIGN KEY (`Id_medico_atencion`) REFERENCES `medico` (`Id_medico`) ON DELETE SET NULL,
  CONSTRAINT `turno_chk_1` CHECK ((`Estado` in (_utf8mb4'reservado',_utf8mb4'cancelado',_utf8mb4'completado',_utf8mb4'ausente')))
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
    DECLARE ya_existe INT DEFAULT 0;
    
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
    
    -- Verificar que el médico atienda ese día/horario
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
    
    -- Verificar que no haya turno duplicado
    SELECT COUNT(*) INTO ya_existe
    FROM turno
    WHERE Id_medico = NEW.Id_medico
    AND Fecha = NEW.Fecha
    AND (Estado IS NULL OR Estado != 'cancelado');
    
    IF ya_existe > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ya existe un turno en ese horario';
    END IF;
    
    -- Valores por defecto
    IF NEW.Estado IS NULL THEN
        SET NEW.Estado = 'reservado';
    END IF;
    
    IF NEW.Atendido IS NULL THEN
        SET NEW.Atendido = FALSE;
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
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `before_turno_update` BEFORE UPDATE ON `turno` FOR EACH ROW BEGIN
    IF NEW.Atendido = TRUE AND OLD.Atendido = FALSE THEN
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

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuario` (
  `Id_usuario` bigint unsigned NOT NULL AUTO_INCREMENT,
  `Nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Apellido` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dni` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Contraseña` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Rol` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paciente',
  `failed_login_attempts` int DEFAULT '0',
  `last_failed_login` timestamp NULL DEFAULT NULL,
  `account_locked_until` timestamp NULL DEFAULT NULL,
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `Fecha_Registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_usuario`),
  UNIQUE KEY `Id_usuario` (`Id_usuario`),
  UNIQUE KEY `dni` (`dni`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_dni` (`dni`),
  KEY `idx_email` (`email`),
  KEY `idx_rol` (`Rol`),
  CONSTRAINT `usuario_chk_1` CHECK ((`Rol` in (_utf8mb4'paciente',_utf8mb4'medico',_utf8mb4'secretaria')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
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

-- Dump completed on 2025-11-14  0:27:23
