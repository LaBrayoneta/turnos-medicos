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
  `diagnostico` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sintomas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fecha_diagnostico` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_diagnostico`),
  UNIQUE KEY `Id_diagnostico` (`Id_diagnostico`),
  KEY `idx_turno` (`Id_turno`),
  KEY `idx_medico` (`Id_medico`),
  KEY `idx_fecha` (`fecha_diagnostico`),
  CONSTRAINT `diagnostico_ibfk_1` FOREIGN KEY (`Id_turno`) REFERENCES `turno` (`Id_turno`) ON DELETE CASCADE,
  CONSTRAINT `diagnostico_ibfk_2` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `diagnostico`
--

LOCK TABLES `diagnostico` WRITE;
/*!40000 ALTER TABLE `diagnostico` DISABLE KEYS */;
INSERT INTO `diagnostico` VALUES (1,1,3,'tiene cancer.','siente dolor en los testiculos y cuando tiene relaciones sexuales no eyacula.','hacer ejercicio y dejar de consumir esteroides.','2025-11-15 04:09:04');
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
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`Id_Especialidad`),
  UNIQUE KEY `Id_Especialidad` (`Id_Especialidad`),
  UNIQUE KEY `Nombre` (`nombre`),
  KEY `idx_activo` (`activo`)
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
  `tipo_registro` enum('consulta','diagnostico','receta','nota','estudio') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'nota',
  `contenido` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_historial`),
  UNIQUE KEY `Id_historial` (`Id_historial`),
  KEY `Id_turno` (`Id_turno`),
  KEY `Id_medico` (`Id_medico`),
  KEY `idx_paciente` (`Id_paciente`),
  KEY `idx_fecha` (`fecha_registro`),
  KEY `idx_tipo` (`tipo_registro`),
  CONSTRAINT `historial_clinico_ibfk_1` FOREIGN KEY (`Id_paciente`) REFERENCES `paciente` (`Id_paciente`) ON DELETE CASCADE,
  CONSTRAINT `historial_clinico_ibfk_2` FOREIGN KEY (`Id_turno`) REFERENCES `turno` (`Id_turno`) ON DELETE SET NULL,
  CONSTRAINT `historial_clinico_ibfk_3` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_clinico`
--

LOCK TABLES `historial_clinico` WRITE;
/*!40000 ALTER TABLE `historial_clinico` DISABLE KEYS */;
INSERT INTO `historial_clinico` VALUES (1,1,1,3,'consulta','DIAGNÓSTICO: tiene cancer.\n\nSÍNTOMAS: siente dolor en los testiculos y cuando tiene relaciones sexuales no eyacula.\n\nOBSERVACIONES: hacer ejercicio y dejar de consumir esteroides.\n\nRECETA:\nLosartán 50mg - 1 comp. por día\nIbuprofeno 400mg - 1 comp. cada 10min','2025-11-15 04:09:04');
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
  `dia_semana` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  PRIMARY KEY (`Id_horario`),
  UNIQUE KEY `Id_horario` (`Id_horario`),
  UNIQUE KEY `unique_horario` (`Id_medico`,`dia_semana`,`hora_inicio`,`hora_fin`),
  KEY `idx_medico_dia` (`Id_medico`,`dia_semana`),
  KEY `idx_horario` (`hora_inicio`,`hora_fin`),
  CONSTRAINT `horario_medico_ibfk_1` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE CASCADE,
  CONSTRAINT `horario_medico_chk_1` CHECK ((`Dia_semana` in (_utf8mb4'lunes',_utf8mb4'martes',_utf8mb4'miercoles',_utf8mb4'jueves',_utf8mb4'viernes',_utf8mb4'sabado',_utf8mb4'domingo')))
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horario_medico`
--

LOCK TABLES `horario_medico` WRITE;
/*!40000 ALTER TABLE `horario_medico` DISABLE KEYS */;
INSERT INTO `horario_medico` VALUES (23,3,'jueves','08:00:00','12:00:00'),(24,3,'lunes','13:00:00','18:00:00');
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
  `nombre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `principio_activo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `presentacion` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dosis_usual` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_medicamento`),
  UNIQUE KEY `Id_medicamento` (`Id_medicamento`),
  KEY `idx_nombre` (`nombre`),
  KEY `idx_activo` (`activo`)
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
  `legajo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `duracion_turno` int DEFAULT '30',
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`Id_medico`),
  UNIQUE KEY `Id_medico` (`Id_medico`),
  UNIQUE KEY `Id_usuario` (`Id_usuario`),
  UNIQUE KEY `Legajo` (`legajo`),
  KEY `idx_especialidad` (`Id_Especialidad`),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `medico_ibfk_1` FOREIGN KEY (`Id_usuario`) REFERENCES `usuario` (`Id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `medico_ibfk_2` FOREIGN KEY (`Id_Especialidad`) REFERENCES `especialidad` (`Id_Especialidad`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medico`
--

LOCK TABLES `medico` WRITE;
/*!40000 ALTER TABLE `medico` DISABLE KEYS */;
INSERT INTO `medico` VALUES (3,5,4,'195-c',30,1);
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
  `nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_obra_social`),
  UNIQUE KEY `Id_obra_social` (`Id_obra_social`),
  UNIQUE KEY `Nombre` (`nombre`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `obra_social`
--

LOCK TABLES `obra_social` WRITE;
/*!40000 ALTER TABLE `obra_social` DISABLE KEYS */;
INSERT INTO `obra_social` VALUES (1,'OSPROTURA',1,'2025-11-14 03:18:32'),(2,'OSMISS',1,'2025-11-14 03:18:32'),(4,'OSTAXBA',1,'2025-11-14 03:18:32'),(5,'OSDE',1,'2025-11-14 03:18:32'),(6,'Swiss Medical',1,'2025-11-14 03:18:32'),(7,'Sin obra social',1,'2025-11-14 03:18:32'),(8,'OTXCARA',1,'2025-11-14 05:16:18');
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
  `nro_carnet` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `libreta_sanitaria` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N/A',
  `activo` tinyint(1) DEFAULT '1',
  `fecha_alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_paciente`),
  UNIQUE KEY `Id_paciente` (`Id_paciente`),
  UNIQUE KEY `Id_usuario` (`Id_usuario`),
  KEY `idx_obra_social` (`Id_obra_social`),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `paciente_ibfk_1` FOREIGN KEY (`Id_usuario`) REFERENCES `usuario` (`Id_usuario`) ON DELETE CASCADE,
  CONSTRAINT `paciente_ibfk_2` FOREIGN KEY (`Id_obra_social`) REFERENCES `obra_social` (`Id_obra_social`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paciente`
--

LOCK TABLES `paciente` WRITE;
/*!40000 ALTER TABLE `paciente` DISABLE KEYS */;
INSERT INTO `paciente` VALUES (1,1,7,'123214','435453',1,'2025-11-14 03:59:46');
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
  `medicamentos` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `indicaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `duracion_tratamiento` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_receta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_vencimiento` date DEFAULT NULL,
  PRIMARY KEY (`Id_receta`),
  UNIQUE KEY `Id_receta` (`Id_receta`),
  KEY `Id_turno` (`Id_turno`),
  KEY `idx_diagnostico` (`Id_diagnostico`),
  KEY `idx_medico` (`Id_medico`),
  KEY `idx_paciente` (`Id_paciente`),
  KEY `idx_fecha_vencimiento` (`fecha_vencimiento`),
  CONSTRAINT `receta_ibfk_1` FOREIGN KEY (`Id_diagnostico`) REFERENCES `diagnostico` (`Id_diagnostico`) ON DELETE CASCADE,
  CONSTRAINT `receta_ibfk_2` FOREIGN KEY (`Id_turno`) REFERENCES `turno` (`Id_turno`) ON DELETE CASCADE,
  CONSTRAINT `receta_ibfk_3` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT,
  CONSTRAINT `receta_ibfk_4` FOREIGN KEY (`Id_paciente`) REFERENCES `paciente` (`Id_paciente`) ON DELETE RESTRICT,
  CONSTRAINT `receta_chk_1` CHECK (((`Fecha_Vencimiento` is null) or (`Fecha_Vencimiento` >= cast(`Fecha_Receta` as date))))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receta`
--

LOCK TABLES `receta` WRITE;
/*!40000 ALTER TABLE `receta` DISABLE KEYS */;
INSERT INTO `receta` VALUES (1,1,1,3,1,'Losartán 50mg - 1 comp. por día\nIbuprofeno 400mg - 1 comp. cada 10min','Ver indicaciones en cada medicamento','hasta que muera','2025-11-15 04:09:04','2025-12-15');
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
  `activo` tinyint(1) DEFAULT '1',
  `fecha_alta` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_secretaria`),
  UNIQUE KEY `Id_secretaria` (`Id_secretaria`),
  UNIQUE KEY `Id_usuario` (`Id_usuario`),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `secretaria_ibfk_1` FOREIGN KEY (`Id_usuario`) REFERENCES `usuario` (`Id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `secretaria`
--

LOCK TABLES `secretaria` WRITE;
/*!40000 ALTER TABLE `secretaria` DISABLE KEYS */;
INSERT INTO `secretaria` VALUES (1,2,1,'2025-11-14 04:02:15');
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
  `fecha` timestamp NOT NULL,
  `estado` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente_confirmacion',
  `fecha_confirmacion` timestamp NULL DEFAULT NULL,
  `Id_staff_confirma` bigint unsigned DEFAULT NULL,
  `motivo_rechazo` text COLLATE utf8mb4_unicode_ci,
  `email_enviado` tinyint(1) DEFAULT '0',
  `atendido` tinyint(1) DEFAULT '0',
  `fecha_atencion` timestamp NULL DEFAULT NULL,
  `Id_medico_atencion` bigint unsigned DEFAULT NULL,
  `observaciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_turno`),
  UNIQUE KEY `Id_turno` (`Id_turno`),
  UNIQUE KEY `unique_turno` (`Id_medico`,`fecha`),
  KEY `Id_secretaria` (`Id_secretaria`),
  KEY `Id_medico_atencion` (`Id_medico_atencion`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_medico_fecha` (`Id_medico`,`fecha`),
  KEY `idx_paciente_fecha` (`Id_paciente`,`fecha`),
  KEY `idx_estado` (`estado`),
  KEY `idx_atendido` (`atendido`),
  KEY `idx_turno_paciente_medico_estado_fecha` (`Id_paciente`,`Id_medico`,`estado`,`fecha`),
  KEY `idx_email_enviado` (`email_enviado`),
  CONSTRAINT `turno_ibfk_1` FOREIGN KEY (`Id_paciente`) REFERENCES `paciente` (`Id_paciente`) ON DELETE RESTRICT,
  CONSTRAINT `turno_ibfk_2` FOREIGN KEY (`Id_medico`) REFERENCES `medico` (`Id_medico`) ON DELETE RESTRICT,
  CONSTRAINT `turno_ibfk_3` FOREIGN KEY (`Id_secretaria`) REFERENCES `secretaria` (`Id_secretaria`) ON DELETE SET NULL,
  CONSTRAINT `turno_ibfk_4` FOREIGN KEY (`Id_medico_atencion`) REFERENCES `medico` (`Id_medico`) ON DELETE SET NULL,
  CONSTRAINT `chk_turno_estado_nuevo` CHECK ((`estado` in (_utf8mb4'pendiente_confirmacion',_utf8mb4'confirmado',_utf8mb4'rechazado',_utf8mb4'reservado',_utf8mb4'cancelado',_utf8mb4'completado',_utf8mb4'ausente'))),
  CONSTRAINT `turno_chk_estados_confirmacion` CHECK ((`estado` in (_utf8mb4'pendiente_confirmacion',_utf8mb4'confirmado',_utf8mb4'rechazado',_utf8mb4'reservado',_utf8mb4'cancelado',_utf8mb4'completado',_utf8mb4'ausente')))
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `turno`
--

LOCK TABLES `turno` WRITE;
/*!40000 ALTER TABLE `turno` DISABLE KEYS */;
INSERT INTO `turno` VALUES (1,1,3,NULL,'2025-11-27 12:30:00','pendiente_confirmacion',NULL,NULL,NULL,0,1,'2025-11-15 04:09:04',3,NULL,'2025-11-14 04:05:48','2025-11-15 19:55:36');
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
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `before_turno_insert_check_duplicate` BEFORE INSERT ON `turno` FOR EACH ROW BEGIN
    DECLARE existing_count INT DEFAULT 0;
    DECLARE error_message VARCHAR(255);
    
    -- Solo validar si el nuevo turno está reservado y es futuro
    IF (NEW.Estado IS NULL OR NEW.Estado = 'reservado') AND NEW.Fecha >= NOW() THEN
        
        -- Contar turnos activos del mismo paciente con el mismo médico
        SELECT COUNT(*) INTO existing_count
        FROM turno
        WHERE Id_paciente = NEW.Id_paciente
          AND Id_medico = NEW.Id_medico
          AND (Estado IS NULL OR Estado = 'reservado')
          AND Fecha >= NOW();
        
        -- Si ya existe al menos un turno activo, lanzar error
        IF existing_count > 0 THEN
            SET error_message = CONCAT(
                'El paciente ID ', NEW.Id_paciente, 
                ' ya tiene un turno activo con el médico ID ', NEW.Id_medico,
                '. Debe cancelar el turno anterior primero.'
            );
            
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = error_message;
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
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dni` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paciente',
  `failed_login_attempts` int DEFAULT '0',
  `last_failed_login` timestamp NULL DEFAULT NULL,
  `account_locked_until` timestamp NULL DEFAULT NULL,
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id_usuario`),
  UNIQUE KEY `Id_usuario` (`Id_usuario`),
  UNIQUE KEY `dni` (`dni`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_dni` (`dni`),
  KEY `idx_email` (`email`),
  KEY `idx_rol` (`rol`),
  CONSTRAINT `usuario_chk_1` CHECK ((`Rol` in (_utf8mb4'paciente',_utf8mb4'medico',_utf8mb4'secretaria')))
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,'Braian','Perez','48654321','braian@gmail.com','$2y$12$UapWp8WCTnSoT6qKOGeEIe8HWSe9KCfsrQNOjxF42IlwG8NfAeDpe','paciente',0,NULL,NULL,'2025-11-15 04:03:22','2025-11-14 03:59:46'),(2,'María','González','30456789','secretaria@clinica.com','$2y$12$YWxgGHt5QX6Ieyh44Oy5hORtA43NdifAKPecM/8zayORasXUzsb36','secretaria',0,NULL,NULL,'2025-11-15 03:43:13','2025-11-14 04:02:15'),(5,'Braian','Salgado','54326478','braian1@gmail.com','$2y$10$TRtyLViiklElHyoigLJJqezSpH1yIVTrYM0d.F3/6bGgUnXzgw6iu','medico',0,NULL,NULL,'2025-11-15 04:03:46','2025-11-14 04:56:17');
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

-- Dump completed on 2025-11-15 17:19:46
