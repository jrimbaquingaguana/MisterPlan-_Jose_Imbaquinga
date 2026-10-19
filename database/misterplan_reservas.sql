-- MySQL dump 10.13  Distrib 8.0.38, for Win64 (x86_64)
--
-- Host: 127.0.0.10   Database: misterplan_reservas
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `reserva`
--

DROP TABLE IF EXISTS `reserva`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reserva` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre_huesped` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_huesped` varchar(254) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_alojamiento` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_entrada` date NOT NULL,
  `fecha_salida` date NOT NULL,
  `estado` enum('pendiente','confirmada','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `importe` decimal(10,2) NOT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reserva_estado` (`estado`),
  KEY `idx_reserva_fecha_entrada` (`fecha_entrada`),
  CONSTRAINT `chk_reserva_fechas` CHECK ((`fecha_salida` > `fecha_entrada`)),
  CONSTRAINT `chk_reserva_importe` CHECK ((`importe` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reserva`
--

LOCK TABLES `reserva` WRITE;
/*!40000 ALTER TABLE `reserva` DISABLE KEYS */;
INSERT INTO `reserva` VALUES (1,'María García','maria.garcia@example.com','Apartamento Sol','2026-11-02','2026-11-06','pendiente',320.00,'Solicita información sobre aparcamiento.','2026-10-10 09:00:00'),(2,'Carlos Muñoz','carlos.munoz@example.com','Casa del Mar','2026-11-10','2026-11-15','confirmada',625.50,'Llegada prevista a las 18:00.','2026-10-10 10:00:00'),(3,'Lucía Fernández','lucia.fernandez@example.com','Hotel Mirador','2026-10-25','2026-10-28','cancelada',270.00,'Preferencia por habitación tranquila.','2026-10-09 11:00:00'),(4,'José Peña','jose.pena@example.com','Casa Rural Encina','2026-12-04','2026-12-08','pendiente',480.00,NULL,'2026-10-11 12:00:00'),(5,'Ana López','ana.lopez@example.com','Apartamento Sol','2026-09-10','2026-09-14','confirmada',300.00,'Viaje de trabajo.','2026-08-20 08:30:00'),(6,'David Sánchez','david.sanchez@example.com','Casa del Mar','2026-12-20','2026-12-23','cancelada',450.00,NULL,'2026-10-12 14:00:00'),(7,'Elena Martín','elena.martin@example.com','Hotel Mirador','2027-01-05','2027-01-07','pendiente',180.00,'Solicita cuna.','2026-10-13 15:00:00'),(8,'Pablo Núñez','pablo.nunez@example.com','Casa Rural Encina','2026-11-20','2026-11-22','confirmada',240.00,'Llegada por la mañana, sujeta a disponibilidad.','2026-10-14 16:00:00'),(9,'Sofía Romero','sofia.romero@example.com','Apartamento Centro','2026-09-18','2026-09-21','cancelada',225.75,'Reserva cancelada antes de la estancia.','2026-08-25 10:00:00');
/*!40000 ALTER TABLE `reserva` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reserva_evento`
--

DROP TABLE IF EXISTS `reserva_evento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reserva_evento` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `reserva_id` int unsigned NOT NULL,
  `tipo` enum('creada','confirmada','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reserva_evento_historial` (`reserva_id`,`fecha_creacion`,`id`),
  CONSTRAINT `fk_reserva_evento_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reserva` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reserva_evento`
--

LOCK TABLES `reserva_evento` WRITE;
/*!40000 ALTER TABLE `reserva_evento` DISABLE KEYS */;
INSERT INTO `reserva_evento` VALUES (1,1,'creada','Reserva creada con estado pendiente.','2026-10-10 09:00:00'),(2,2,'creada','Reserva creada con estado pendiente.','2026-10-10 10:00:00'),(3,2,'confirmada','Estado cambiado de pendiente a confirmada.','2026-10-10 12:00:00'),(4,3,'creada','Reserva creada con estado pendiente.','2026-10-09 11:00:00'),(5,3,'cancelada','Estado cambiado de pendiente a cancelada.','2026-10-10 11:30:00'),(6,4,'creada','Reserva creada con estado pendiente.','2026-10-11 12:00:00'),(7,5,'creada','Reserva creada con estado pendiente.','2026-08-20 08:30:00'),(8,5,'confirmada','Estado cambiado de pendiente a confirmada.','2026-08-21 09:00:00'),(9,6,'creada','Reserva creada con estado pendiente.','2026-10-12 14:00:00'),(10,6,'confirmada','Estado cambiado de pendiente a confirmada.','2026-10-12 17:00:00'),(11,6,'cancelada','Estado cambiado de confirmada a cancelada.','2026-10-14 10:00:00'),(12,7,'creada','Reserva creada con estado pendiente.','2026-10-13 15:00:00'),(13,8,'creada','Reserva creada con estado pendiente.','2026-10-14 16:00:00'),(14,8,'confirmada','Estado cambiado de pendiente a confirmada.','2026-10-15 09:30:00'),(15,9,'creada','Reserva creada con estado pendiente.','2026-08-25 10:00:00'),(16,9,'confirmada','Estado cambiado de pendiente a confirmada.','2026-08-26 11:00:00'),(17,9,'cancelada','Estado cambiado de confirmada a cancelada.','2026-09-01 12:00:00');
/*!40000 ALTER TABLE `reserva_evento` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-16 19:41:14
