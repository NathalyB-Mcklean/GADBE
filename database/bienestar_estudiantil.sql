-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Dec 14, 2025 at 03:03 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bienestar_estudiantil`
--

-- --------------------------------------------------------

--
-- Table structure for table `auditoria`
--

DROP TABLE IF EXISTS `auditoria`;
CREATE TABLE IF NOT EXISTS `auditoria` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `accion` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tabla_afectada` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registro_id` int DEFAULT NULL,
  `detalles` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_accion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_fecha` (`fecha_accion`),
  KEY `idx_auditoria_usuario` (`usuario_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auditoria`
--

INSERT INTO `auditoria` (`id`, `usuario_id`, `accion`, `tabla_afectada`, `registro_id`, `detalles`, `ip_address`, `fecha_accion`) VALUES
(1, 6, 'login', 'usuarios', 6, NULL, '127.0.0.1', '2025-12-14 01:34:02'),
(2, 6, 'logout', 'usuarios', 6, NULL, '127.0.0.1', '2025-12-14 01:34:57'),
(3, 7, 'login', 'usuarios', 7, NULL, '127.0.0.1', '2025-12-14 01:37:14'),
(4, 7, 'eliminar_servicio', 'servicios', 4, NULL, '127.0.0.1', '2025-12-14 01:47:53'),
(5, 7, 'logout', 'usuarios', 7, NULL, '127.0.0.1', '2025-12-14 01:55:51'),
(6, 6, 'login', 'usuarios', 6, NULL, '127.0.0.1', '2025-12-14 01:56:06'),
(7, 6, 'logout', 'usuarios', 6, NULL, '127.0.0.1', '2025-12-14 02:30:41');

-- --------------------------------------------------------

--
-- Table structure for table `bloqueos_horarios`
--

DROP TABLE IF EXISTS `bloqueos_horarios`;
CREATE TABLE IF NOT EXISTS `bloqueos_horarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `trabajador_social_id` int NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `motivo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('vacaciones','ausencia','capacitacion','otro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `trabajador_social_id` (`trabajador_social_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `activa` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`, `descripcion`, `activa`) VALUES
(1, 'Orientación psicológica', 'Servicios de apoyo psicológico y emocional', 1),
(2, 'Equiparación de oportunidades', 'Becas y apoyos económicos', 1),
(3, 'Cultura', 'Actividades culturales y artísticas', 1),
(4, 'Deportes', 'Actividades deportivas y recreativas', 1),
(5, 'Apoyo académico', 'Tutorías y asesorías académicas', 1),
(6, 'Salud', 'Servicios de salud y bienestar físico', 1);

-- --------------------------------------------------------

--
-- Table structure for table `citas`
--

DROP TABLE IF EXISTS `citas`;
CREATE TABLE IF NOT EXISTS `citas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estudiante_id` int NOT NULL,
  `servicio_id` int NOT NULL,
  `trabajador_social_id` int NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `motivo` text COLLATE utf8mb4_unicode_ci,
  `estado` enum('pendiente','confirmada','cancelada','completada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cita` (`fecha`,`hora`,`trabajador_social_id`),
  KEY `servicio_id` (`servicio_id`),
  KEY `trabajador_social_id` (`trabajador_social_id`),
  KEY `idx_citas_fecha` (`fecha`),
  KEY `idx_citas_estudiante` (`estudiante_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluaciones`
--

DROP TABLE IF EXISTS `evaluaciones`;
CREATE TABLE IF NOT EXISTS `evaluaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estudiante_id` int NOT NULL,
  `servicio_id` int DEFAULT NULL,
  `calificacion` enum('Excelente','Bueno','Regular','Malo','Muy Malo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `comentario` text COLLATE utf8mb4_unicode_ci,
  `fecha_evaluacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `estudiante_id` (`estudiante_id`),
  KEY `idx_evaluaciones_servicio` (`servicio_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `horarios_disponibles`
--

DROP TABLE IF EXISTS `horarios_disponibles`;
CREATE TABLE IF NOT EXISTS `horarios_disponibles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `trabajador_social_id` int NOT NULL,
  `dia_semana` enum('Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `trabajador_social_id` (`trabajador_social_id`)
) ENGINE=MyISAM AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `horarios_disponibles`
--

INSERT INTO `horarios_disponibles` (`id`, `trabajador_social_id`, `dia_semana`, `hora_inicio`, `hora_fin`, `activo`) VALUES
(1, 2, 'Lunes', '08:00:00', '12:00:00', 1),
(2, 2, 'Lunes', '14:00:00', '17:00:00', 1),
(3, 2, 'Martes', '08:00:00', '12:00:00', 1),
(4, 2, 'Martes', '14:00:00', '17:00:00', 1),
(5, 2, 'Miércoles', '08:00:00', '12:00:00', 1),
(6, 2, 'Miércoles', '14:00:00', '17:00:00', 1),
(7, 2, 'Jueves', '08:00:00', '12:00:00', 1),
(8, 2, 'Jueves', '14:00:00', '18:00:00', 1),
(9, 2, 'Viernes', '08:00:00', '12:00:00', 1),
(10, 3, 'Lunes', '08:00:00', '12:00:00', 1),
(11, 3, 'Lunes', '14:00:00', '17:00:00', 1),
(12, 3, 'Martes', '08:00:00', '12:00:00', 1),
(13, 3, 'Martes', '14:00:00', '17:00:00', 1),
(14, 3, 'Miércoles', '08:00:00', '12:00:00', 1),
(15, 3, 'Miércoles', '14:00:00', '17:00:00', 1),
(16, 3, 'Jueves', '08:00:00', '12:00:00', 1),
(17, 3, 'Jueves', '14:00:00', '17:00:00', 1),
(18, 3, 'Viernes', '08:00:00', '12:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `permisos`
--

DROP TABLE IF EXISTS `permisos`;
CREATE TABLE IF NOT EXISTS `permisos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `modulo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=MyISAM AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permisos`
--

INSERT INTO `permisos` (`id`, `nombre`, `descripcion`, `modulo`) VALUES
(1, 'ver_servicios', 'Ver catálogo de servicios y ofertas', 'servicios'),
(2, 'crear_servicios', 'Crear nuevos servicios y ofertas', 'servicios'),
(3, 'modificar_servicios', 'Modificar servicios y ofertas existentes', 'servicios'),
(4, 'eliminar_servicios', 'Eliminar servicios y ofertas', 'servicios'),
(5, 'programar_citas', 'Programar citas para servicios', 'citas'),
(6, 'ver_mis_citas', 'Ver mis citas programadas', 'citas'),
(7, 'ver_todas_citas', 'Ver todas las citas del departamento', 'citas'),
(8, 'modificar_citas', 'Modificar citas existentes', 'citas'),
(9, 'cancelar_citas', 'Cancelar citas', 'citas'),
(10, 'gestionar_agenda', 'Gestionar agenda de atención', 'citas'),
(11, 'realizar_evaluaciones', 'Realizar evaluaciones de satisfacción', 'evaluaciones'),
(12, 'ver_resultados_evaluaciones', 'Ver resultados de evaluaciones', 'evaluaciones'),
(13, 'generar_estadisticas', 'Generar estadísticas y reportes', 'evaluaciones'),
(14, 'solicitar_servicios', 'Solicitar servicios de bienestar', 'solicitudes'),
(15, 'ver_mis_solicitudes', 'Ver mis solicitudes', 'solicitudes'),
(16, 'gestionar_solicitudes', 'Revisar y gestionar solicitudes', 'solicitudes'),
(17, 'aprobar_solicitudes', 'Aprobar o rechazar solicitudes', 'solicitudes'),
(18, 'gestionar_usuarios', 'Administrar usuarios del sistema', 'usuarios'),
(19, 'gestionar_roles', 'Administrar roles y permisos', 'usuarios'),
(20, 'ver_auditoria', 'Ver registros de auditoría', 'usuarios');

-- --------------------------------------------------------

--
-- Table structure for table `roles_permisos`
--

DROP TABLE IF EXISTS `roles_permisos`;
CREATE TABLE IF NOT EXISTS `roles_permisos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rol` enum('Estudiante','Trabajadora Social','Administrador') COLLATE utf8mb4_unicode_ci NOT NULL,
  `permiso_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rol_permiso` (`rol`,`permiso_id`),
  KEY `permiso_id` (`permiso_id`)
) ENGINE=MyISAM AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles_permisos`
--

INSERT INTO `roles_permisos` (`id`, `rol`, `permiso_id`) VALUES
(1, 'Estudiante', 1),
(2, 'Estudiante', 5),
(3, 'Estudiante', 6),
(4, 'Estudiante', 8),
(5, 'Estudiante', 9),
(6, 'Estudiante', 11),
(7, 'Estudiante', 14),
(8, 'Estudiante', 15),
(9, 'Trabajadora Social', 1),
(10, 'Trabajadora Social', 2),
(11, 'Trabajadora Social', 3),
(12, 'Trabajadora Social', 7),
(13, 'Trabajadora Social', 8),
(14, 'Trabajadora Social', 10),
(15, 'Trabajadora Social', 12),
(16, 'Trabajadora Social', 13),
(17, 'Trabajadora Social', 16),
(18, 'Trabajadora Social', 17),
(19, 'Administrador', 1),
(20, 'Administrador', 2),
(21, 'Administrador', 3),
(22, 'Administrador', 4),
(23, 'Administrador', 5),
(24, 'Administrador', 6),
(25, 'Administrador', 7),
(26, 'Administrador', 8),
(27, 'Administrador', 9),
(28, 'Administrador', 10),
(29, 'Administrador', 11),
(30, 'Administrador', 12),
(31, 'Administrador', 13),
(32, 'Administrador', 14),
(33, 'Administrador', 15),
(34, 'Administrador', 16),
(35, 'Administrador', 17),
(36, 'Administrador', 18),
(37, 'Administrador', 19),
(38, 'Administrador', 20);

-- --------------------------------------------------------

--
-- Table structure for table `servicios`
--

DROP TABLE IF EXISTS `servicios`;
CREATE TABLE IF NOT EXISTS `servicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('Servicio','Oferta') COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria_id` int NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `trabajador_social_id` int NOT NULL,
  `ubicacion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT 'Por definir',
  `fecha_limite` date DEFAULT NULL,
  `estado` enum('activo','proximamente','finalizado') COLLATE utf8mb4_unicode_ci DEFAULT 'activo',
  `duracion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Por definir',
  `fecha_publicacion` date NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_nombre` (`nombre`),
  KEY `trabajador_social_id` (`trabajador_social_id`),
  KEY `idx_servicios_estado` (`estado`),
  KEY `idx_servicios_categoria` (`categoria_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `servicios`
--

INSERT INTO `servicios` (`id`, `tipo`, `categoria_id`, `nombre`, `descripcion`, `trabajador_social_id`, `ubicacion`, `fecha_limite`, `estado`, `duracion`, `fecha_publicacion`, `fecha_creacion`, `fecha_modificacion`) VALUES
(1, 'Servicio', 1, 'Apoyo Psicológico', 'Consultas individuales con psicólogos especializados en problemas estudiantiles. Ofrecemos apoyo emocional, estrategias de manejo de estrés y orientación vocacional.', 2, 'Edificio A, Piso 3', '2025-12-31', 'activo', '1 hora', '2025-01-15', '2025-12-14 01:20:06', '2025-12-14 01:20:06'),
(2, 'Oferta', 2, 'Beca de Alimentación', 'Subsidio para estudiantes de bajos recursos que cubre hasta el 80% del costo de alimentación en el comedor universitario.', 3, 'Edificio B, Oficina 205', '2025-08-15', 'activo', 'Semestral', '2025-02-20', '2025-12-14 01:20:06', '2025-12-14 01:20:06'),
(3, 'Servicio', 3, 'Taller de Danza Folclórica', 'Talleres semanales de danza folclórica para estudiantes interesados en mantener vivas las tradiciones culturales de la región.', 2, 'Centro Cultural', '2025-10-10', 'activo', '2 horas', '2025-03-05', '2025-12-14 01:20:06', '2025-12-14 01:20:06');

-- --------------------------------------------------------

--
-- Table structure for table `solicitudes`
--

DROP TABLE IF EXISTS `solicitudes`;
CREATE TABLE IF NOT EXISTS `solicitudes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estudiante_id` int NOT NULL,
  `servicio_id` int NOT NULL,
  `estado` enum('borrador','pendiente','en_revision','aprobada','rechazada') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `motivo` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `documentos` json DEFAULT NULL,
  `comentarios_trabajador` text COLLATE utf8mb4_unicode_ci,
  `fecha_solicitud` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_revision` timestamp NULL DEFAULT NULL,
  `revisado_por` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `estudiante_id` (`estudiante_id`),
  KEY `servicio_id` (`servicio_id`),
  KEY `revisado_por` (`revisado_por`),
  KEY `idx_solicitudes_estado` (`estado`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `correo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('Estudiante','Trabajadora Social','Administrador') COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `intentos_fallidos` int DEFAULT '0',
  `bloqueado_hasta` datetime DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ultima_sesion` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `correo` (`correo`)
) ;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id`, `correo`, `password_hash`, `nombre`, `rol`, `activo`, `intentos_fallidos`, `bloqueado_hasta`, `fecha_creacion`, `ultima_sesion`) VALUES
(1, 'admin@utp.ac.pa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador Sistema', 'Administrador', 1, 1, NULL, '2025-12-14 01:20:06', NULL),
(2, 'maria.gonzalez@utp.ac.pa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dra. María González', 'Trabajadora Social', 1, 0, NULL, '2025-12-14 01:20:06', NULL),
(3, 'carlos.ramirez@utp.ac.pa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lic. Carlos Ramírez', 'Trabajadora Social', 1, 0, NULL, '2025-12-14 01:20:06', NULL),
(4, 'estudiante1@utp.ac.pa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Pérez', 'Estudiante', 1, 0, NULL, '2025-12-14 01:20:06', NULL),
(5, 'estudiante2@utp.ac.pa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Laura Martínez', 'Estudiante', 1, 0, NULL, '2025-12-14 01:20:06', NULL),
(6, 'nathaly@utp.ac.pa', '$2y$10$ALknaUlDW8A5G.SoZDfM8e6FUI5tdpnZqI/EULO6OLpHzHaXZK1MC', 'Nathaly Bonilla', 'Estudiante', 1, 0, NULL, '2025-12-14 01:33:48', '2025-12-14 01:56:06'),
(7, 'admin1@utp.ac.pa', '$2y$10$vgL3HyoZEQL4bL1TBzEHdOgo6YzwnLPmrNUdMoZSrnPFUB04488Ka', 'Administrador', 'Administrador', 1, 0, NULL, '2025-12-14 01:36:15', '2025-12-14 01:37:14');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
