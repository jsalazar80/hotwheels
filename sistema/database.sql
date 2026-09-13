/*
SQLyog Ultimate v10.00 Beta1
MySQL - 5.5.5-10.4.32-MariaDB : Database - db_techsupport
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`db_techsupport` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `db_techsupport`;

/*Table structure for table `tbl_admin_user` */

DROP TABLE IF EXISTS `tbl_admin_user`;

CREATE TABLE `tbl_admin_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `usuario` varchar(60) NOT NULL,
  `password` varchar(255) NOT NULL,
  `id_tbl_profiles` int(11) NOT NULL,
  `ultimo_acceso` datetime DEFAULT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `fk_admin_user_perfil` (`id_tbl_profiles`),
  CONSTRAINT `fk_admin_user_perfil` FOREIGN KEY (`id_tbl_profiles`) REFERENCES `tbl_profiles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_admin_user` */

insert  into `tbl_admin_user`(`id`,`nombre`,`usuario`,`password`,`id_tbl_profiles`,`ultimo_acceso`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'Daniela Macías','dmacias','$2y$10$VTIKUqrCKEWuWxPUBC6MleWHTGpLGyZfHPuGWxg5e.sjqopGpMJFO',2,NULL,1,NULL,'2026-07-20 22:00:09'),(2,'Julián Salazar','master','$2y$10$sH.MGSwtpL/eXinyCVkqxucDy3zzg/sTnQ7kmAF.aFgbqCUTNgwYu',1,'2026-07-21 06:40:04',1,NULL,'2026-07-20 22:00:39');

/*Table structure for table `tbl_bancos` */

DROP TABLE IF EXISTS `tbl_bancos`;

CREATE TABLE `tbl_bancos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_bancos` */

insert  into `tbl_bancos`(`id`,`nombre`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'No Aplica',1,1,'2026-07-20 22:00:09'),(2,'Banco Pichincha',1,1,'2026-07-20 22:00:09'),(3,'Banco Guayaquil',1,1,'2026-07-20 22:00:09'),(4,'Banco del Pacífico',1,1,'2026-07-20 22:00:09'),(5,'Produbanco',1,1,'2026-07-20 22:00:09'),(6,'Banco Bolivariano',1,1,'2026-07-20 22:00:09'),(7,'Banco Internacional',1,1,'2026-07-20 22:00:09'),(8,'Banco de Machala',1,1,'2026-07-20 22:00:09'),(9,'Banco General Rumiñahui',1,1,'2026-07-20 22:00:09'),(10,'Banco ProCredit',1,1,'2026-07-20 22:00:09'),(11,'Banco Solidario',1,1,'2026-07-20 22:00:09'),(12,'Diners Club del Ecuador',1,1,'2026-07-20 22:00:09'),(13,'Banco de Loja',1,1,'2026-07-20 22:00:09'),(14,'Banco Amazonas',1,1,'2026-07-20 22:00:09'),(15,'Banco Coopnacional',1,1,'2026-07-20 22:00:09'),(16,'Banco Finca',1,1,'2026-07-20 22:00:09'),(17,'Banco D-MIRO',1,1,'2026-07-20 22:00:09'),(18,'Banco VisionFund Ecuador',1,1,'2026-07-20 22:00:09'),(19,'Banco Central del Ecuador',1,1,'2026-07-20 22:00:09'),(20,'Cooperativa JEP',1,1,'2026-07-20 22:00:09'),(21,'Cooperativa Jardín Azuayo',1,1,'2026-07-20 22:00:09'),(22,'Cooperativa Policía Nacional',1,1,'2026-07-20 22:00:09'),(23,'Cooperativa 29 de Octubre',1,1,'2026-07-20 22:00:09'),(24,'Cooperativa Andalucía',1,1,'2026-07-20 22:00:09'),(25,'Cooperativa Alianza del Valle',1,1,'2026-07-20 22:00:09'),(26,'Cooperativa Cotocollao',1,1,'2026-07-20 22:00:09'),(27,'Cooperativa Riobamba',1,1,'2026-07-20 22:00:09'),(28,'Cooperativa OSCUS',1,1,'2026-07-20 22:00:09'),(29,'Cooperativa San Francisco',1,1,'2026-07-20 22:00:09'),(30,'Cooperativa CODESARROLLO',1,1,'2026-07-20 22:00:09');

/*Table structure for table `tbl_bancos_cuentas` */

DROP TABLE IF EXISTS `tbl_bancos_cuentas`;

CREATE TABLE `tbl_bancos_cuentas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tbl_bancos` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_cuenta_banco` (`id_tbl_bancos`),
  CONSTRAINT `fk_cuenta_banco` FOREIGN KEY (`id_tbl_bancos`) REFERENCES `tbl_bancos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_bancos_cuentas` */

insert  into `tbl_bancos_cuentas`(`id`,`id_tbl_bancos`,`nombre`,`state`,`user_ing`,`fecha_hora_ing`) values (1,1,'0000000000',1,1,'2026-07-20 22:00:09'),(2,6,'645-132327-8',1,2,'2026-07-20 22:09:00');

/*Table structure for table `tbl_clientes` */

DROP TABLE IF EXISTS `tbl_clientes`;

CREATE TABLE `tbl_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tbl_tipo_persona` int(11) NOT NULL,
  `rucci` varchar(13) NOT NULL COMMENT 'Cedula (10 digitos) o RUC (13 digitos)',
  `nombre_comercial` varchar(150) NOT NULL,
  `razon_social` varchar(150) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `correo` varchar(150) NOT NULL,
  `valor_por_hora_ref` decimal(7,2) NOT NULL DEFAULT 0.00,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rucci` (`rucci`),
  UNIQUE KEY `uk_correo` (`correo`),
  KEY `fk_cliente_tipo_persona` (`id_tbl_tipo_persona`),
  CONSTRAINT `fk_cliente_tipo_persona` FOREIGN KEY (`id_tbl_tipo_persona`) REFERENCES `tbl_tipo_persona` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_clientes` */

insert  into `tbl_clientes`(`id`,`id_tbl_tipo_persona`,`rucci`,`nombre_comercial`,`razon_social`,`direccion`,`telefono`,`celular`,`correo`,`state`,`user_ing`,`fecha_hora_ing`) values (1,2,'0923564871001','Pescafoods','Pescafoodsas','','','','compras@pescafoodsas.com',1,2,'2026-07-20 22:05:43'),(2,2,'0932165487001','Muchoseafood','Muchoseafood S.A.','','','','compras@muchoseafood.com',1,2,'2026-07-20 22:13:46'),(3,2,'0945678123001','Notaria 42','Notaria 42','','','','mmontenegro@notaria42.com',1,2,'2026-07-20 22:17:02');

/*Table structure for table `tbl_configuracion` */

DROP TABLE IF EXISTS `tbl_configuracion`;

CREATE TABLE `tbl_configuracion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_empresa` varchar(150) NOT NULL DEFAULT 'TechSupport',
  `logo` varchar(255) DEFAULT NULL,
  `prefijo_ticket` varchar(10) NOT NULL DEFAULT 'TK-',
  `siguiente_numero` int(11) NOT NULL DEFAULT 1,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_configuracion` */

insert  into `tbl_configuracion`(`id`,`nombre_empresa`,`logo`,`prefijo_ticket`,`siguiente_numero`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'TechSupport',NULL,'TK-',4,1,NULL,'2026-07-20 22:00:09');

/*Table structure for table `tbl_email_config_auth` */

DROP TABLE IF EXISTS `tbl_email_config_auth`;

CREATE TABLE `tbl_email_config_auth` (
  `id` varchar(5) NOT NULL,
  `nombre` varchar(5) NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_email_config_auth` */

insert  into `tbl_email_config_auth`(`id`,`nombre`,`state`) values ('true','true',1),('false','false',1);

/*Table structure for table `tbl_email_config_port` */

DROP TABLE IF EXISTS `tbl_email_config_port`;

CREATE TABLE `tbl_email_config_port` (
  `id` varchar(4) NOT NULL,
  `nombre` varchar(4) NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_email_config_port` */

insert  into `tbl_email_config_port`(`id`,`nombre`,`state`) values ('25','25',1),('465','465',1),('587','587',1);

/*Table structure for table `tbl_email_config_scrt` */

DROP TABLE IF EXISTS `tbl_email_config_scrt`;

CREATE TABLE `tbl_email_config_scrt` (
  `id` varchar(3) NOT NULL,
  `nombre` varchar(3) NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_email_config_scrt` */

insert  into `tbl_email_config_scrt`(`id`,`nombre`,`state`) values ('ssl','ssl',1),('tls','tls',1);

/*Table structure for table `tbl_email_config_sender` */

DROP TABLE IF EXISTS `tbl_email_config_sender`;

CREATE TABLE `tbl_email_config_sender` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ml_username` varchar(100) NOT NULL DEFAULT 'julisalazarcarrillo@gmail.com',
  `ml_password` varchar(20) NOT NULL DEFAULT 'uczd yohz hbkj bzlx',
  `ml_sent_by_name` varchar(100) NOT NULL DEFAULT 'Zamlo',
  `ml_host` varchar(100) NOT NULL DEFAULT 'smtp.gmail.com',
  `id_tbl_email_config_port` varchar(4) NOT NULL DEFAULT '465',
  `id_tbl_email_config_auth` varchar(5) NOT NULL DEFAULT 'true',
  `id_tbl_email_config_scrt` varchar(3) NOT NULL DEFAULT 'ssl',
  `ml_smtp_relay` int(11) NOT NULL DEFAULT 250,
  `cc` varchar(150) DEFAULT NULL,
  `bcc` varchar(150) DEFAULT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_email_sender_port` (`id_tbl_email_config_port`),
  KEY `fk_email_sender_auth` (`id_tbl_email_config_auth`),
  KEY `fk_email_sender_scrt` (`id_tbl_email_config_scrt`),
  CONSTRAINT `fk_email_sender_port` FOREIGN KEY (`id_tbl_email_config_port`) REFERENCES `tbl_email_config_port` (`id`),
  CONSTRAINT `fk_email_sender_auth` FOREIGN KEY (`id_tbl_email_config_auth`) REFERENCES `tbl_email_config_auth` (`id`),
  CONSTRAINT `fk_email_sender_scrt` FOREIGN KEY (`id_tbl_email_config_scrt`) REFERENCES `tbl_email_config_scrt` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_email_config_sender` */

insert  into `tbl_email_config_sender`(`id`,`ml_username`,`ml_password`,`ml_sent_by_name`,`ml_host`,`id_tbl_email_config_port`,`id_tbl_email_config_auth`,`id_tbl_email_config_scrt`,`ml_smtp_relay`,`cc`,`bcc`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'julisalazarcarrillo@gmail.com','uczd yohz hbkj bzlx','Zamlo','smtp.gmail.com','465','true','ssl',250,NULL,NULL,1,NULL,current_timestamp());

/*Table structure for table `tbl_email_send` */

DROP TABLE IF EXISTS `tbl_email_send`;

CREATE TABLE `tbl_email_send` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_email` int(11) NOT NULL DEFAULT 1,
  `table_id` int(11) DEFAULT NULL,
  `titl_email` varchar(255) DEFAULT NULL,
  `body_email` longtext DEFAULT NULL,
  `send_email` varchar(150) DEFAULT NULL,
  `dest_email` varchar(150) DEFAULT NULL,
  `cc_email` varchar(150) DEFAULT NULL,
  `bcc_email` varchar(150) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  `state` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_email_send_dest` (`dest_email`),
  KEY `idx_email_send_tabla` (`type_email`, `table_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Table structure for table `tbl_general_auditory` */

DROP TABLE IF EXISTS `tbl_general_auditory`;

CREATE TABLE `tbl_general_auditory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(120) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `accion` enum('INS','UPD','DEL','SEL') NOT NULL,
  `tabla` varchar(80) NOT NULL,
  `id_registro` int(11) DEFAULT NULL,
  `query_ejecutado` text DEFAULT NULL,
  `observaciones` longtext DEFAULT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_auditory_tabla` (`tabla`),
  KEY `idx_auditory_fecha` (`fecha_hora`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_general_auditory` */

insert  into `tbl_general_auditory`(`id`,`usuario`,`fecha_hora`,`accion`,`tabla`,`id_registro`,`query_ejecutado`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'Julián Salazar','2026-07-20 22:01:13','UPD','tbl_soporte_categoria',0,'Reordenamiento por arrastre: 1,2,3,4,5,6',1,NULL,'2026-07-20 22:01:13'),(2,'Julián Salazar','2026-07-20 22:01:19','UPD','tbl_soporte_tipo',0,'Reordenamiento por arrastre: 2,1,3,4,5,6,7',1,NULL,'2026-07-20 22:01:19'),(3,'Julián Salazar','2026-07-20 22:01:22','UPD','tbl_soporte_tipo',0,'Reordenamiento por arrastre: 1,2,3,4,5,6,7',1,NULL,'2026-07-20 22:01:22'),(4,'Julián Salazar','2026-07-20 22:01:26','UPD','tbl_soporte_prioridad',0,'Reordenamiento por arrastre: 1,2,3,4',1,NULL,'2026-07-20 22:01:26'),(5,'Julián Salazar','2026-07-20 22:02:29','UPD','tbl_menu_admin',7,'UPDATE tbl_menu_admin SET nombre=?, url=?, icono=?, orden=?, is_submenu=? WHERE id=?',1,NULL,'2026-07-20 22:02:29'),(6,'Julián Salazar','2026-07-20 22:02:45','UPD','tbl_menu_admin',8,'UPDATE tbl_menu_admin SET nombre=?, url=?, icono=?, orden=?, is_submenu=? WHERE id=?',1,NULL,'2026-07-20 22:02:45'),(7,'Julián Salazar','2026-07-20 22:02:53','UPD','tbl_menu_admin',15,'Reordenamiento por arrastre (padre=15): 8,7',1,NULL,'2026-07-20 22:02:53'),(8,'Julián Salazar','2026-07-20 22:03:08','UPD','tbl_menu_admin',9,'Reordenamiento por arrastre (padre=9): 10,14,11,12,13,16,17',1,NULL,'2026-07-20 22:03:08'),(9,'Julián Salazar','2026-07-20 22:03:23','UPD','tbl_menu_admin',5,'UPDATE tbl_menu_admin SET nombre=?, url=?, icono=?, orden=?, is_submenu=? WHERE id=?',1,NULL,'2026-07-20 22:03:23'),(10,'Julián Salazar','2026-07-20 22:03:31','UPD','tbl_menu_admin',6,'UPDATE tbl_menu_admin SET nombre=?, url=?, icono=?, orden=?, is_submenu=? WHERE id=?',1,NULL,'2026-07-20 22:03:31'),(11,'Julián Salazar','2026-07-20 22:03:44','UPD','tbl_menu_admin',0,'Reordenamiento por arrastre (padre=0): 1,2,3,18,4,9,15,19',1,NULL,'2026-07-20 22:03:44'),(12,'Julián Salazar','2026-07-20 22:03:46','UPD','tbl_menu_admin',0,'Reordenamiento por arrastre (padre=0): 1,3,2,18,4,9,15,19',1,NULL,'2026-07-20 22:03:46'),(13,'Julián Salazar','2026-07-20 22:03:57','UPD','tbl_menu_admin',4,'UPDATE tbl_menu_admin SET nombre=?, url=?, icono=?, orden=?, is_submenu=? WHERE id=?',1,NULL,'2026-07-20 22:03:57'),(14,'Julián Salazar','2026-07-20 22:04:06','UPD','tbl_menu_admin',9,'Reordenamiento por arrastre (padre=9): 10,14,11,12,13,16,17,6,5,4',1,NULL,'2026-07-20 22:04:06'),(15,'Julián Salazar','2026-07-20 22:04:17','UPD','tbl_menu_admin',0,'Reordenamiento por arrastre (padre=0): 1,3,2,18,19,9,15',1,NULL,'2026-07-20 22:04:17'),(16,'Julián Salazar','2026-07-20 22:05:43','INS','tbl_clientes',1,'INSERT INTO tbl_clientes (id_tbl_tipo_persona, rucci, nombre_comercial, razon_social, direccion, telefono, celular, correo, user_ing) VALUES (?,?,?,?,?,?,?,?,?)',1,NULL,'2026-07-20 22:05:43'),(17,'Julián Salazar','2026-07-20 22:06:24','INS','tbl_soportes',1,'INSERT INTO tbl_soportes\n            (numero, fecha, id_tbl_clientes, solicitante, asunto, descripcion, id_tbl_soporte_categoria, id_tbl_soporte_tipo, id_tbl_soporte_prioridad, id_tbl_admin_user_asignado, valor_por_hora, state, user_ing)\n            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',1,NULL,'2026-07-20 22:06:24'),(18,'Julián Salazar','2026-07-20 22:07:22','UPD','tbl_soportes',1,'UPDATE tbl_soportes SET\n            id_tbl_soporte_categoria=?, id_tbl_soporte_tipo=?, id_tbl_soporte_prioridad=?, id_tbl_admin_user_asignado=?,\n            solicitante=?, descripcion=?, analisis=?, solucion=?, observacion=?, recomendacion=?,\n            fecha_hora_solved_str=?, fecha_hora_solved_end=?, valor_por_hora=?, fecha_cierre=?, state=?\n            WHERE id=?',1,NULL,'2026-07-20 22:07:22'),(19,'Julián Salazar','2026-07-20 22:07:22','INS','tbl_soportes_archivos',1,'INSERT INTO tbl_soportes_archivos (id_tbl_soportes, archivo, nombre_original, tipo_archivo, user_ing) VALUES (?,?,?,?,?)',1,NULL,'2026-07-20 22:07:22'),(20,'Julián Salazar','2026-07-20 22:07:22','INS','tbl_soportes_archivos',2,'INSERT INTO tbl_soportes_archivos (id_tbl_soportes, archivo, nombre_original, tipo_archivo, user_ing) VALUES (?,?,?,?,?)',1,NULL,'2026-07-20 22:07:22'),(21,'Julián Salazar','2026-07-20 22:07:23','INS','tbl_soportes_archivos',3,'INSERT INTO tbl_soportes_archivos (id_tbl_soportes, archivo, nombre_original, tipo_archivo, user_ing) VALUES (?,?,?,?,?)',1,NULL,'2026-07-20 22:07:23'),(22,'Julián Salazar','2026-07-20 22:07:23','INS','tbl_soportes_archivos',4,'INSERT INTO tbl_soportes_archivos (id_tbl_soportes, archivo, nombre_original, tipo_archivo, user_ing) VALUES (?,?,?,?,?)',1,NULL,'2026-07-20 22:07:23'),(23,'Julián Salazar','2026-07-20 22:07:30','UPD','tbl_soportes',1,'UPDATE tbl_soportes SET\n            id_tbl_soporte_categoria=?, id_tbl_soporte_tipo=?, id_tbl_soporte_prioridad=?, id_tbl_admin_user_asignado=?,\n            solicitante=?, descripcion=?, analisis=?, solucion=?, observacion=?, recomendacion=?,\n            fecha_hora_solved_str=?, fecha_hora_solved_end=?, valor_por_hora=?, fecha_cierre=?, state=?\n            WHERE id=?',1,NULL,'2026-07-20 22:07:30'),(24,'Julián Salazar','2026-07-20 22:07:30','INS','tbl_soportes_seguimiento',1,'INSERT INTO tbl_soportes_seguimiento (id_tbl_soportes, comentario, id_tbl_soporte_estado, user_ing) VALUES (?,?,?,?)',1,NULL,'2026-07-20 22:07:30'),(25,'Julián Salazar','2026-07-20 22:07:37','UPD','tbl_soportes',1,'UPDATE tbl_soportes SET\n            id_tbl_soporte_categoria=?, id_tbl_soporte_tipo=?, id_tbl_soporte_prioridad=?, id_tbl_admin_user_asignado=?,\n            solicitante=?, descripcion=?, analisis=?, solucion=?, observacion=?, recomendacion=?,\n            fecha_hora_solved_str=?, fecha_hora_solved_end=?, valor_por_hora=?, fecha_cierre=?, state=?\n            WHERE id=?',1,NULL,'2026-07-20 22:07:37'),(26,'Julián Salazar','2026-07-20 22:07:43','UPD','tbl_soportes',1,'UPDATE tbl_soportes SET\n            id_tbl_soporte_categoria=?, id_tbl_soporte_tipo=?, id_tbl_soporte_prioridad=?, id_tbl_admin_user_asignado=?,\n            solicitante=?, descripcion=?, analisis=?, solucion=?, observacion=?, recomendacion=?,\n            fecha_hora_solved_str=?, fecha_hora_solved_end=?, valor_por_hora=?, fecha_cierre=?, state=?\n            WHERE id=?',1,NULL,'2026-07-20 22:07:43'),(27,'Julián Salazar','2026-07-20 22:07:43','INS','tbl_soportes_seguimiento',2,'INSERT INTO tbl_soportes_seguimiento (id_tbl_soportes, comentario, id_tbl_soporte_estado, user_ing) VALUES (?,?,?,?)',1,NULL,'2026-07-20 22:07:43'),(28,'Julián Salazar','2026-07-20 22:09:00','INS','tbl_bancos_cuentas',2,'INSERT INTO tbl_bancos_cuentas (id_tbl_bancos, nombre, user_ing) VALUES (?,?,?)',1,NULL,'2026-07-20 22:09:00'),(29,'Julián Salazar','2026-07-20 22:09:56','INS','tbl_pagos',1,'INSERT INTO tbl_pagos (id_tbl_clientes, fecha, id_tbl_tipos_pago, monto, id_tbl_bancos, no_cuenta, ref_no, id_tbl_bancos_cuentas, observaciones, user_ing) VALUES (?,?,?,?,?,?,?,?,?,?)',1,NULL,'2026-07-20 22:09:56'),(30,'Julián Salazar','2026-07-20 22:11:02','UPD','tbl_admin_user',1,'UPDATE tbl_admin_user SET nombre=?, usuario=?, id_tbl_profiles=?, password=? WHERE id=?',1,NULL,'2026-07-20 22:11:02'),(31,'Julián Salazar','2026-07-20 22:13:46','INS','tbl_clientes',2,'INSERT INTO tbl_clientes (id_tbl_tipo_persona, rucci, nombre_comercial, razon_social, direccion, telefono, celular, correo, user_ing) VALUES (?,?,?,?,?,?,?,?,?)',1,NULL,'2026-07-20 22:13:46'),(32,'Julián Salazar','2026-07-20 22:14:39','INS','tbl_soportes',2,'INSERT INTO tbl_soportes\n            (numero, fecha, id_tbl_clientes, solicitante, asunto, descripcion, id_tbl_soporte_categoria, id_tbl_soporte_tipo, id_tbl_soporte_prioridad, id_tbl_admin_user_asignado, valor_por_hora, state, user_ing)\n            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',1,NULL,'2026-07-20 22:14:39'),(33,'Julián Salazar','2026-07-20 22:15:35','UPD','tbl_soportes',2,'UPDATE tbl_soportes SET\n            id_tbl_soporte_categoria=?, id_tbl_soporte_tipo=?, id_tbl_soporte_prioridad=?, id_tbl_admin_user_asignado=?,\n            solicitante=?, descripcion=?, analisis=?, solucion=?, observacion=?, recomendacion=?,\n            fecha_hora_solved_str=?, fecha_hora_solved_end=?, valor_por_hora=?, fecha_cierre=?, state=?\n            WHERE id=?',1,NULL,'2026-07-20 22:15:35'),(34,'Julián Salazar','2026-07-20 22:16:02','UPD','tbl_soportes',2,'UPDATE tbl_soportes SET\n            id_tbl_soporte_categoria=?, id_tbl_soporte_tipo=?, id_tbl_soporte_prioridad=?, id_tbl_admin_user_asignado=?,\n            solicitante=?, descripcion=?, analisis=?, solucion=?, observacion=?, recomendacion=?,\n            fecha_hora_solved_str=?, fecha_hora_solved_end=?, valor_por_hora=?, fecha_cierre=?, state=?\n            WHERE id=?',1,NULL,'2026-07-20 22:16:02'),(35,'Julián Salazar','2026-07-20 22:17:02','INS','tbl_clientes',3,'INSERT INTO tbl_clientes (id_tbl_tipo_persona, rucci, nombre_comercial, razon_social, direccion, telefono, celular, correo, user_ing) VALUES (?,?,?,?,?,?,?,?,?)',1,NULL,'2026-07-20 22:17:02'),(36,'Julián Salazar','2026-07-20 22:17:51','INS','tbl_soportes',3,'INSERT INTO tbl_soportes\n            (numero, fecha, id_tbl_clientes, solicitante, asunto, descripcion, id_tbl_soporte_categoria, id_tbl_soporte_tipo, id_tbl_soporte_prioridad, id_tbl_admin_user_asignado, valor_por_hora, state, user_ing)\n            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',1,NULL,'2026-07-20 22:17:51'),(37,'Julián Salazar','2026-07-20 22:18:30','UPD','tbl_soportes',3,'UPDATE tbl_soportes SET\n            id_tbl_soporte_categoria=?, id_tbl_soporte_tipo=?, id_tbl_soporte_prioridad=?, id_tbl_admin_user_asignado=?,\n            solicitante=?, descripcion=?, analisis=?, solucion=?, observacion=?, recomendacion=?,\n            fecha_hora_solved_str=?, fecha_hora_solved_end=?, valor_por_hora=?, fecha_cierre=?, state=?\n            WHERE id=?',1,NULL,'2026-07-20 22:18:30'),(38,'Julián Salazar','2026-07-20 22:18:38','UPD','tbl_soportes',3,'UPDATE tbl_soportes SET\n            id_tbl_soporte_categoria=?, id_tbl_soporte_tipo=?, id_tbl_soporte_prioridad=?, id_tbl_admin_user_asignado=?,\n            solicitante=?, descripcion=?, analisis=?, solucion=?, observacion=?, recomendacion=?,\n            fecha_hora_solved_str=?, fecha_hora_solved_end=?, valor_por_hora=?, fecha_cierre=?, state=?\n            WHERE id=?',1,NULL,'2026-07-20 22:18:38'),(39,'Julián Salazar','2026-07-20 22:18:38','INS','tbl_soportes_seguimiento',3,'INSERT INTO tbl_soportes_seguimiento (id_tbl_soportes, comentario, id_tbl_soporte_estado, user_ing) VALUES (?,?,?,?)',1,NULL,'2026-07-20 22:18:38'),(40,'Julián Salazar','2026-07-21 03:21:31','UPD','tbl_soporte_categoria',0,'Reordenamiento por arrastre: 1,2,3,4,5,6',1,NULL,'2026-07-21 03:21:31'),(41,'Julián Salazar','2026-07-21 03:21:35','UPD','tbl_soporte_categoria',0,'Reordenamiento por arrastre: 1,2,3,4,5,6',1,NULL,'2026-07-21 03:21:35'),(42,'Julián Salazar','2026-07-21 03:21:43','UPD','tbl_soporte_categoria',0,'Reordenamiento por arrastre: 1,2,3,4,5,6',1,NULL,'2026-07-21 03:21:43'),(43,'Julián Salazar','2026-07-21 03:21:52','UPD','tbl_soporte_categoria',0,'Reordenamiento por arrastre: 1,2,3,4,5,6',1,NULL,'2026-07-21 03:21:52'),(44,'Julián Salazar','2026-07-21 03:21:55','UPD','tbl_soporte_categoria',0,'Reordenamiento por arrastre: 1,2,3,4,5,6',1,NULL,'2026-07-21 03:21:55'),(45,'Julián Salazar','2026-07-21 10:18:29','UPD','tbl_menu_admin',0,'Reordenamiento por arrastre (padre=0): 1,3,2,18,19,9,15',1,NULL,'2026-07-21 10:18:29'),(46,'Julián Salazar','2026-07-21 10:18:42','UPD','tbl_menu_admin',0,'Reordenamiento por arrastre (padre=0): 3,2,18,1,19,9,15',1,NULL,'2026-07-21 10:18:42'),(47,'Julián Salazar','2026-07-21 10:19:24','UPD','tbl_menu_admin',0,'Reordenamiento por arrastre (padre=0): 1,3,2,18,19,9,15',1,NULL,'2026-07-21 10:19:24');

/*Table structure for table `tbl_import_type` */

DROP TABLE IF EXISTS `tbl_import_type`;

CREATE TABLE `tbl_import_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_import_type` */

insert  into `tbl_import_type`(`id`,`nombre`,`state`) values (1,'Previsualizar',1),(2,'Importar',1);

/*Table structure for table `tbl_imports` */

DROP TABLE IF EXISTS `tbl_imports`;

CREATE TABLE `tbl_imports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_import_type` int(11) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `fecha_hora_str` datetime DEFAULT NULL,
  `fecha_hora_end` datetime DEFAULT NULL,
  `state` int(11) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_imports_tipo` (`id_import_type`),
  KEY `fk_imports_usuario` (`user_ing`),
  CONSTRAINT `fk_imports_tipo` FOREIGN KEY (`id_import_type`) REFERENCES `tbl_import_type` (`id`),
  CONSTRAINT `fk_imports_usuario` FOREIGN KEY (`user_ing`) REFERENCES `tbl_admin_user` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Table structure for table `tbl_login` */

DROP TABLE IF EXISTS `tbl_login`;

CREATE TABLE `tbl_login` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) DEFAULT NULL,
  `usuario` varchar(120) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL,
  `navegador` varchar(100) DEFAULT NULL,
  `dispositivo` varchar(100) DEFAULT NULL,
  `observacion` enum('LOGIN','LOGOUT') NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_usuario` (`usuario`),
  KEY `idx_login_fecha` (`fecha_hora`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_login` */

insert  into `tbl_login`(`id`,`session_id`,`usuario`,`fecha_hora`,`ip`,`navegador`,`dispositivo`,`observacion`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'trc9tns0214v3suflmonnso69e','Julián Salazar','2026-07-20 22:00:13','::1','Google Chrome','Escritorio','LOGOUT',1,2,'2026-07-20 22:00:13'),(2,'trc9tns0214v3suflmonnso69e','Julián Salazar','2026-07-20 22:00:47','::1','Google Chrome','Escritorio','LOGIN',1,2,'2026-07-20 22:00:47'),(3,'8o9te7er9c66i5dijerduptlls','Administrador','2026-07-20 22:35:15','172.16.1.51','Google Chrome','Escritorio','LOGOUT',1,2,'2026-07-20 22:35:15'),(4,'8o9te7er9c66i5dijerduptlls','Julián Salazar','2026-07-20 22:35:44','172.16.1.51','Google Chrome','Escritorio','LOGIN',1,2,'2026-07-20 22:35:44'),(5,'trc9tns0214v3suflmonnso69e','Julián Salazar','2026-07-21 06:39:54','::1','Google Chrome','Escritorio','LOGOUT',1,2,'2026-07-21 06:39:54'),(6,'trc9tns0214v3suflmonnso69e','Julián Salazar','2026-07-21 06:40:04','::1','Google Chrome','Escritorio','LOGIN',1,2,'2026-07-21 06:40:04');

/*Table structure for table `tbl_menu_admin` */

DROP TABLE IF EXISTS `tbl_menu_admin`;

CREATE TABLE `tbl_menu_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `url` varchar(150) NOT NULL,
  `icono` varchar(50) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `is_submenu` int(11) NOT NULL DEFAULT 0,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_menu_admin` */

insert  into `tbl_menu_admin`(`id`,`nombre`,`url`,`icono`,`orden`,`is_submenu`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'Dashboard','index.php','bi-speedometer2',1,0,1,NULL,'2026-07-20 22:00:09'),(2,'Soportes','soportes.php','bi-life-preserver',3,0,1,NULL,'2026-07-20 22:00:09'),(3,'Clientes','clientes.php','bi-people',2,0,1,NULL,'2026-07-20 22:00:09'),(4,'Usuarios','usuarios.php','bi-person-badge',10,9,1,NULL,'2026-07-20 22:00:09'),(5,'Perfiles','perfiles.php','bi-shield-lock',9,9,1,NULL,'2026-07-20 22:00:09'),(6,'Menús','menus.php','bi-list-ul',8,9,1,NULL,'2026-07-20 22:00:09'),(7,'Auditoría','auditoria.php','bi-clipboard-data',2,15,1,NULL,'2026-07-20 22:00:09'),(8,'Accesos al Sistema','accesos.php','bi-door-open',1,15,1,NULL,'2026-07-20 22:00:09'),(9,'Configuración','#','bi-gear',7,0,1,NULL,'2026-07-20 22:00:09'),(10,'Datos de la Empresa','cnf_empresa.php','bi-building',1,9,1,NULL,'2026-07-20 22:00:09'),(11,'Categorías de Soporte','cnf_soporte_categoria.php','bi-tags',3,9,1,NULL,'2026-07-20 22:00:09'),(12,'Tipos de Soporte','cnf_soporte_tipo.php','bi-diagram-3',4,9,1,NULL,'2026-07-20 22:00:09'),(13,'Prioridades de Soporte','cnf_soporte_prioridad.php','bi-exclamation-diamond',5,9,1,NULL,'2026-07-20 22:00:09'),(14,'Tipo de Persona','cnf_tipo_persona.php','bi-person-vcard',2,9,1,NULL,'2026-07-20 22:00:09'),(15,'Supervisor','#','bi-person-workspace',8,0,1,NULL,'2026-07-20 22:00:09'),(16,'Configurar Bancos','cnf_bancos.php','bi-bank',6,9,1,NULL,'2026-07-20 22:00:09'),(17,'Configurar Cuentas Bancarias','cnf_cuentas_bancarias.php','bi-wallet2',7,9,1,NULL,'2026-07-20 22:00:09'),(18,'Pagos','pagos.php','bi-cash-coin',4,0,1,NULL,'2026-07-20 22:00:09'),(19,'Saldos','saldos.php','bi-cash-stack',5,0,1,NULL,'2026-07-20 22:00:09'),(20,'Configurar Correo','cnf_email.php','bi-envelope-gear',11,9,1,NULL,'2026-07-23 00:00:00'),(21,'Generar Datos de Prueba','generar_prueba.php','bi-magic',3,15,1,NULL,'2026-07-24 00:00:00'),(22,'Importar Soportes','importar_soportes.php','bi-file-earmark-excel',12,9,1,NULL,'2026-07-24 00:00:00'),(23,'Actualizar Saldos','actualizar_saldos.php','bi-arrow-repeat',13,9,1,NULL,'2026-07-24 00:00:00'),(24,'Tipos de Pago','cnf_tipos_pago.php','bi-credit-card',14,9,1,NULL,'2026-07-25 00:00:00'),(25,'Reportes','#','bi-file-earmark-bar-graph',6,0,1,NULL,'2026-07-25 00:00:00'),(26,'Soportes','reporte_soportes.php','bi-life-preserver',1,25,1,NULL,'2026-07-25 00:00:00');

/*Table structure for table `tbl_pagos` */

DROP TABLE IF EXISTS `tbl_pagos`;

CREATE TABLE `tbl_pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tbl_clientes` int(11) NOT NULL,
  `fecha` datetime NOT NULL,
  `id_tbl_tipos_pago` int(11) NOT NULL,
  `monto` decimal(12,2) NOT NULL DEFAULT 0.00,
  `id_tbl_bancos` int(11) DEFAULT NULL,
  `no_cuenta` varchar(50) DEFAULT NULL,
  `ref_no` varchar(50) DEFAULT NULL,
  `id_tbl_bancos_cuentas` int(11) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_pago_cliente` (`id_tbl_clientes`),
  KEY `fk_pago_tipo` (`id_tbl_tipos_pago`),
  KEY `fk_pago_banco` (`id_tbl_bancos`),
  KEY `fk_pago_cuenta` (`id_tbl_bancos_cuentas`),
  CONSTRAINT `fk_pago_banco` FOREIGN KEY (`id_tbl_bancos`) REFERENCES `tbl_bancos` (`id`),
  CONSTRAINT `fk_pago_cliente` FOREIGN KEY (`id_tbl_clientes`) REFERENCES `tbl_clientes` (`id`),
  CONSTRAINT `fk_pago_cuenta` FOREIGN KEY (`id_tbl_bancos_cuentas`) REFERENCES `tbl_bancos_cuentas` (`id`),
  CONSTRAINT `fk_pago_tipo` FOREIGN KEY (`id_tbl_tipos_pago`) REFERENCES `tbl_tipos_pago` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_pagos` */

insert  into `tbl_pagos`(`id`,`id_tbl_clientes`,`fecha`,`id_tbl_tipos_pago`,`monto`,`id_tbl_bancos`,`no_cuenta`,`ref_no`,`id_tbl_bancos_cuentas`,`observaciones`,`state`,`user_ing`,`fecha_hora_ing`) values (1,1,'2026-07-20',2,'45.00',6,'6546422','162132546',2,'',1,2,'2026-07-20 22:09:56');

/*Table structure for table `tbl_pagos_archivos` */

DROP TABLE IF EXISTS `tbl_pagos_archivos`;

CREATE TABLE `tbl_pagos_archivos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tbl_pagos` int(11) NOT NULL,
  `archivo` varchar(255) NOT NULL COMMENT 'Nombre de archivo dentro de files/pagos/folder_{id_tbl_pagos}',
  `nombre_original` varchar(255) DEFAULT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_archivo_pago` (`id_tbl_pagos`),
  CONSTRAINT `fk_archivo_pago` FOREIGN KEY (`id_tbl_pagos`) REFERENCES `tbl_pagos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_pagos_archivos` */

/*Table structure for table `tbl_profiles` */

DROP TABLE IF EXISTS `tbl_profiles`;

CREATE TABLE `tbl_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `permisos` text DEFAULT NULL COMMENT 'Lista de ids de tbl_menu_admin separados por coma',
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_profiles` */

insert  into `tbl_profiles`(`id`,`nombre`,`permisos`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'Master','1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26',1,NULL,'2026-07-20 22:00:09'),(2,'Administrador','1,2,3,4,18,19',1,NULL,'2026-07-20 22:00:09'),(3,'Técnico','1,2,3',1,NULL,'2026-07-20 22:00:09');

/*Table structure for table `tbl_saldos` */

DROP TABLE IF EXISTS `tbl_saldos`;

CREATE TABLE `tbl_saldos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tbl_clientes` int(11) NOT NULL,
  `total_soportes` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_pagos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_saldo_cliente` (`id_tbl_clientes`),
  CONSTRAINT `fk_saldo_cliente` FOREIGN KEY (`id_tbl_clientes`) REFERENCES `tbl_clientes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_saldos` */

insert  into `tbl_saldos`(`id`,`id_tbl_clientes`,`total_soportes`,`total_pagos`,`state`,`user_ing`,`fecha_hora_ing`) values (1,1,'45.00','45.00',1,NULL,'2026-07-20 22:07:22'),(2,2,'0.00','0.00',1,NULL,'2026-07-20 22:15:35'),(3,3,'0.00','0.00',1,NULL,'2026-07-20 22:18:30');

/*Table structure for table `tbl_soporte_categoria` */

DROP TABLE IF EXISTS `tbl_soporte_categoria`;

CREATE TABLE `tbl_soporte_categoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_soporte_categoria` */

insert  into `tbl_soporte_categoria`(`id`,`nombre`,`orden`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'Asistencia técnica',1,1,1,'2026-07-20 22:00:09'),(2,'Programación',2,1,1,'2026-07-20 22:00:09'),(3,'Biométrico',3,1,1,'2026-07-20 22:00:09'),(4,'Asesoría/Consultoría',4,1,1,'2026-07-20 22:00:09'),(5,'Proyecto',5,1,1,'2026-07-20 22:00:09'),(6,'Mantenimiento y Respaldo',6,1,1,'2026-07-20 22:00:09');

/*Table structure for table `tbl_soporte_estado` */

DROP TABLE IF EXISTS `tbl_soporte_estado`;

CREATE TABLE `tbl_soporte_estado` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `icono` varchar(50) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL COMMENT 'Color hexadecimal, ej: #dc3545',
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_soporte_estado` */

insert  into `tbl_soporte_estado`(`id`,`nombre`,`icono`,`color`,`state`,`user_ing`,`fecha_hora_ing`) values (0,'ANULADO','bi-x-circle-fill','#6c757d',1,1,'2026-07-20 22:00:09'),(1,'REPORTADO','bi-exclamation-circle-fill','#dc3545',1,1,'2026-07-20 22:00:09'),(2,'EN PROCESO','bi-arrow-repeat','#fd7e14',1,1,'2026-07-20 22:00:09'),(3,'SOLUCIONADO','bi-check-circle-fill','#0d6efd',1,1,'2026-07-20 22:00:09'),(4,'FINALIZADO','bi-check2-all','#198754',1,1,'2026-07-20 22:00:09');

/*Table structure for table `tbl_soporte_prioridad` */

DROP TABLE IF EXISTS `tbl_soporte_prioridad`;

CREATE TABLE `tbl_soporte_prioridad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT 'secondary',
  `orden` int(11) NOT NULL DEFAULT 0,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_soporte_prioridad` */

insert  into `tbl_soporte_prioridad`(`id`,`nombre`,`color`,`orden`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'Baja','secondary',1,1,NULL,'2026-07-20 22:00:09'),(2,'Media','info',2,1,NULL,'2026-07-20 22:00:09'),(3,'Alta','warning',3,1,NULL,'2026-07-20 22:00:09'),(4,'Urgente','danger',4,1,NULL,'2026-07-20 22:00:09');

/*Table structure for table `tbl_soporte_tipo` */

DROP TABLE IF EXISTS `tbl_soporte_tipo`;

CREATE TABLE `tbl_soporte_tipo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_soporte_tipo` */

insert  into `tbl_soporte_tipo`(`id`,`nombre`,`orden`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'Presencial',1,1,1,'2026-07-20 22:00:09'),(2,'Remoto',2,1,1,'2026-07-20 22:00:09'),(3,'Telefónica',3,1,1,'2026-07-20 22:00:09'),(4,'Whatsapp',4,1,1,'2026-07-20 22:00:09'),(5,'Telefónico + Remoto',5,1,1,'2026-07-20 22:00:09'),(6,'Whatsapp + Remoto',6,1,1,'2026-07-20 22:00:09'),(7,'Telefónico + Whatsapp + Remoto',7,1,1,'2026-07-20 22:00:09');

/*Table structure for table `tbl_soportes` */

DROP TABLE IF EXISTS `tbl_soportes`;

CREATE TABLE `tbl_soportes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL,
  `fecha` datetime NOT NULL,
  `id_tbl_clientes` int(11) NOT NULL,
  `solicitante` varchar(100) DEFAULT NULL,
  `asunto` varchar(200) NOT NULL,
  `descripcion` longtext DEFAULT NULL,
  `analisis` longtext DEFAULT NULL,
  `solucion` longtext DEFAULT NULL,
  `observacion` longtext DEFAULT NULL,
  `recomendacion` longtext DEFAULT NULL,
  `id_tbl_soporte_categoria` int(11) DEFAULT NULL,
  `id_tbl_soporte_tipo` int(11) DEFAULT NULL,
  `id_tbl_soporte_prioridad` int(11) NOT NULL,
  `id_tbl_admin_user_asignado` int(11) DEFAULT NULL COMMENT 'Técnico asignado',
  `fecha_hora_solved_str` datetime DEFAULT NULL COMMENT 'Inicio de atención',
  `fecha_hora_solved_end` datetime DEFAULT NULL COMMENT 'Fin de atención',
  `total_minutos` int(11) NOT NULL DEFAULT 0 COMMENT 'fecha_hora_solved_end - fecha_hora_solved_str en minutos',
  `valor_por_hora` decimal(7,2) NOT NULL DEFAULT 0.00,
  `monto_total` decimal(7,2) NOT NULL DEFAULT 0.00 COMMENT '(total_minutos/60)*valor_por_hora',
  `fecha_cierre` datetime DEFAULT NULL,
  `state` int(11) NOT NULL DEFAULT 1 COMMENT 'FK a tbl_soporte_estado',
  `import_id` int(11) DEFAULT NULL COMMENT 'FK a tbl_imports, si el registro proviene de una importación',
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `fk_soporte_cliente` (`id_tbl_clientes`),
  KEY `fk_soporte_categoria` (`id_tbl_soporte_categoria`),
  KEY `fk_soporte_tipo` (`id_tbl_soporte_tipo`),
  KEY `fk_soporte_prioridad` (`id_tbl_soporte_prioridad`),
  KEY `fk_soporte_asignado` (`id_tbl_admin_user_asignado`),
  KEY `fk_soporte_estado` (`state`),
  KEY `fk_soporte_usuario` (`user_ing`),
  KEY `fk_soporte_import` (`import_id`),
  CONSTRAINT `fk_soporte_asignado` FOREIGN KEY (`id_tbl_admin_user_asignado`) REFERENCES `tbl_admin_user` (`id`),
  CONSTRAINT `fk_soporte_categoria` FOREIGN KEY (`id_tbl_soporte_categoria`) REFERENCES `tbl_soporte_categoria` (`id`),
  CONSTRAINT `fk_soporte_cliente` FOREIGN KEY (`id_tbl_clientes`) REFERENCES `tbl_clientes` (`id`),
  CONSTRAINT `fk_soporte_estado` FOREIGN KEY (`state`) REFERENCES `tbl_soporte_estado` (`id`),
  CONSTRAINT `fk_soporte_import` FOREIGN KEY (`import_id`) REFERENCES `tbl_imports` (`id`),
  CONSTRAINT `fk_soporte_prioridad` FOREIGN KEY (`id_tbl_soporte_prioridad`) REFERENCES `tbl_soporte_prioridad` (`id`),
  CONSTRAINT `fk_soporte_tipo` FOREIGN KEY (`id_tbl_soporte_tipo`) REFERENCES `tbl_soporte_tipo` (`id`),
  CONSTRAINT `fk_soporte_usuario` FOREIGN KEY (`user_ing`) REFERENCES `tbl_admin_user` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_soportes` */

insert  into `tbl_soportes`(`id`,`numero`,`fecha`,`id_tbl_clientes`,`solicitante`,`asunto`,`descripcion`,`analisis`,`solucion`,`observacion`,`recomendacion`,`id_tbl_soporte_categoria`,`id_tbl_soporte_tipo`,`id_tbl_soporte_prioridad`,`id_tbl_admin_user_asignado`,`fecha_hora_solved_str`,`fecha_hora_solved_end`,`total_minutos`,`valor_por_hora`,`monto_total`,`fecha_cierre`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'TK-000001','2026-07-20',1,'Ma. Alexandra Buchelli','Cambio de Bateria laptop Glarrea','Cambio de Bateria laptop Glarrea','','','','',1,1,4,2,'2026-07-20 21:06:00','2026-07-20 22:06:00',60,'45.00','45.00','2026-07-20 22:07:43',4,2,'2026-07-20 22:06:24'),(2,'TK-000002','2026-07-20',2,'Gabriela Larrea','Revisión de Laptop','Revisión de Laptop','','','','',4,6,2,2,'2026-07-20 20:15:00','2026-07-20 22:15:00',120,'45.00','90.00',NULL,1,2,'2026-07-20 22:14:39'),(3,'TK-000003','2026-07-20',3,'Keysha','Revisión Cámaras','Revisión Cámaras','','','','',6,5,4,2,'2026-07-20 21:17:00','2026-07-20 22:18:00',61,'45.00','45.75',NULL,2,2,'2026-07-20 22:17:51');

/*Table structure for table `tbl_soportes_archivos` */

DROP TABLE IF EXISTS `tbl_soportes_archivos`;

CREATE TABLE `tbl_soportes_archivos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tbl_soportes` int(11) NOT NULL,
  `archivo` varchar(255) NOT NULL COMMENT 'Nombre de archivo dentro de files/soportes',
  `nombre_original` varchar(255) DEFAULT NULL,
  `tipo_archivo` varchar(20) DEFAULT NULL COMMENT 'imagen o video',
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_archivo_soporte` (`id_tbl_soportes`),
  CONSTRAINT `fk_archivo_soporte` FOREIGN KEY (`id_tbl_soportes`) REFERENCES `tbl_soportes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_soportes_archivos` */

insert  into `tbl_soportes_archivos`(`id`,`id_tbl_soportes`,`archivo`,`nombre_original`,`tipo_archivo`,`state`,`user_ing`,`fecha_hora_ing`) values (1,1,'soporte_1_1784603242_0.jpg','foto1.jpg','imagen',1,2,'2026-07-20 22:07:22'),(2,1,'soporte_1_1784603242_1.jpg','foto2.jpg','imagen',1,2,'2026-07-20 22:07:22'),(3,1,'soporte_1_1784603242_2.jpg','foto3.jpg','imagen',1,2,'2026-07-20 22:07:22'),(4,1,'soporte_1_1784603243_3.jpg','foto4.jpg','imagen',1,2,'2026-07-20 22:07:23');

/*Table structure for table `tbl_soportes_preview` */

DROP TABLE IF EXISTS `tbl_soportes_preview`;

CREATE TABLE `tbl_soportes_preview` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `import_id` int(11) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `id_tbl_clientes` int(11) DEFAULT NULL,
  `solicitante` varchar(100) DEFAULT NULL,
  `asunto` varchar(200) DEFAULT NULL,
  `descripcion` longtext DEFAULT NULL,
  `analisis` longtext DEFAULT NULL,
  `solucion` longtext DEFAULT NULL,
  `observacion` longtext DEFAULT NULL,
  `recomendacion` longtext DEFAULT NULL,
  `id_tbl_soporte_categoria` int(11) DEFAULT NULL,
  `id_tbl_soporte_tipo` int(11) DEFAULT NULL,
  `id_tbl_soporte_prioridad` int(11) DEFAULT NULL,
  `id_tbl_admin_user_asignado` int(11) DEFAULT NULL COMMENT 'Técnico asignado',
  `fecha_hora_solved_str` datetime DEFAULT NULL COMMENT 'Inicio de atención',
  `fecha_hora_solved_end` datetime DEFAULT NULL COMMENT 'Fin de atención',
  `total_minutos` int(11) DEFAULT 0,
  `valor_por_hora` decimal(7,2) DEFAULT 0.00,
  `monto_total` decimal(7,2) DEFAULT 0.00,
  `fecha_cierre` datetime DEFAULT NULL,
  `state` int(11) DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_preview_import` (`import_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Copia de tbl_soportes usada como área de staging para previsualizar importaciones; sin FKs porque se trunca en cada previsualización.';

/*Table structure for table `tbl_soportes_seguimiento` */

DROP TABLE IF EXISTS `tbl_soportes_seguimiento`;

CREATE TABLE `tbl_soportes_seguimiento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tbl_soportes` int(11) NOT NULL,
  `comentario` text NOT NULL,
  `id_tbl_soporte_estado` int(11) DEFAULT NULL COMMENT 'Estado al que se cambió con este seguimiento (si aplica)',
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_seguimiento_soporte` (`id_tbl_soportes`),
  KEY `fk_seguimiento_estado` (`id_tbl_soporte_estado`),
  CONSTRAINT `fk_seguimiento_estado` FOREIGN KEY (`id_tbl_soporte_estado`) REFERENCES `tbl_soporte_estado` (`id`),
  CONSTRAINT `fk_seguimiento_soporte` FOREIGN KEY (`id_tbl_soportes`) REFERENCES `tbl_soportes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_soportes_seguimiento` */

insert  into `tbl_soportes_seguimiento`(`id`,`id_tbl_soportes`,`comentario`,`id_tbl_soporte_estado`,`state`,`user_ing`,`fecha_hora_ing`) values (1,1,'Cambio de estado a \"EN PROCESO\".',2,1,2,'2026-07-20 22:07:30'),(2,1,'Cambio de estado a \"FINALIZADO\".',4,1,2,'2026-07-20 22:07:43'),(3,3,'Cambio de estado a \"EN PROCESO\".',2,1,2,'2026-07-20 22:18:38');

/*Table structure for table `tbl_tipo_persona` */

DROP TABLE IF EXISTS `tbl_tipo_persona`;

CREATE TABLE `tbl_tipo_persona` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_tipo_persona` */

insert  into `tbl_tipo_persona`(`id`,`nombre`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'Natural',1,1,'2026-07-20 22:00:09'),(2,'Jurídica',1,1,'2026-07-20 22:00:09');

/*Table structure for table `tbl_tipos_pago` */

DROP TABLE IF EXISTS `tbl_tipos_pago`;

CREATE TABLE `tbl_tipos_pago` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 1,
  `user_ing` int(11) DEFAULT NULL,
  `fecha_hora_ing` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tbl_tipos_pago` */

insert  into `tbl_tipos_pago`(`id`,`nombre`,`state`,`user_ing`,`fecha_hora_ing`) values (1,'Efectivo',1,1,'2026-07-20 22:00:09'),(2,'Transferencia',1,1,'2026-07-20 22:00:09'),(3,'Cheque',1,1,'2026-07-20 22:00:09'),(4,'Crédito',1,1,'2026-07-20 22:00:09');

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
