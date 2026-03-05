CREATE DATABASE IF NOT EXISTS `si-jadwal_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `si-jadwal_db`;

CREATE TABLE IF NOT EXISTS `user` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(191) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `schedule` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(191) NOT NULL,
  `set_id` VARCHAR(64) NOT NULL,
  `name` VARCHAR(191) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 0,
  `no_col` VARCHAR(50) NULL,
  `kode` VARCHAR(100) NULL,
  `nama_matakuliah` VARCHAR(255) NULL,
  `sks` VARCHAR(10) NULL,
  `kelas` VARCHAR(50) NULL,
  `pengampu` VARCHAR(255) NULL,
  `jenis` VARCHAR(50) NULL,
  `ruang` VARCHAR(100) NULL,
  `hari` VARCHAR(20) NULL,
  `jam_mulai` VARCHAR(20) NULL,
  `jam_selesai` VARCHAR(20) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_user_set` (`username`, `set_id`),
  KEY `idx_user_active` (`username`, `is_active`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `task` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(191) NOT NULL,
  `mata_kuliah` VARCHAR(255) NULL,
  `jenis` VARCHAR(100) NULL,
  `tanggal` DATE NULL,
  `jam` TIME NULL,
  `status` ENUM('Belum selesai','Selesai','Arsip') DEFAULT 'Belum selesai',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_user_status` (`username`, `status`),
  KEY `idx_user_tanggal` (`username`, `tanggal`)
) ENGINE=InnoDB;
