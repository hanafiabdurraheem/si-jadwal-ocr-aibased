CREATE DATABASE IF NOT EXISTS `si-jadwal_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `si-jadwal_db`;

CREATE TABLE IF NOT EXISTS `user` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(191) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `user_preference` (
  `username` VARCHAR(191) NOT NULL PRIMARY KEY,
  `theme_color` ENUM('ungu','kuning','biru','hijau','magenta') NOT NULL DEFAULT 'ungu',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `kelas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `owner_username` VARCHAR(191) NOT NULL,
  `nama` VARCHAR(191) NOT NULL,
  `kode_join` VARCHAR(12) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_owner` (`owner_username`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `kelas_member` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kelas_id` INT NOT NULL,
  `username` VARCHAR(191) NOT NULL,
  `role` ENUM('admin','member') NOT NULL DEFAULT 'member',
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_kelas_user` (`kelas_id`, `username`),
  KEY `idx_user` (`username`),
  KEY `idx_kelas` (`kelas_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `kelas_jadwal_set` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kelas_id` INT NOT NULL,
  `set_key` VARCHAR(64) NOT NULL UNIQUE,
  `name` VARCHAR(191) NOT NULL,
  `created_by` VARCHAR(191) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_kelas` (`kelas_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `kelas_jadwal_item` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kelas_jadwal_set_id` INT NOT NULL,
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
  KEY `idx_set` (`kelas_jadwal_set_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `kelas_tugas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kelas_id` INT NOT NULL,
  `created_by` VARCHAR(191) NOT NULL,
  `mata_kuliah` VARCHAR(255) NULL,
  `jenis` VARCHAR(100) NULL,
  `tanggal` DATE NULL,
  `jam` TIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_kelas` (`kelas_id`),
  KEY `idx_tanggal` (`tanggal`)
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
  `mode` ENUM('luring','daring','asingkron') NOT NULL DEFAULT 'luring',
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
  `source_kelas_id` INT NULL,
  `source_kelas_tugas_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_user_status` (`username`, `status`),
  KEY `idx_user_tanggal` (`username`, `tanggal`),
  KEY `idx_user_kelas_task` (`username`, `source_kelas_tugas_id`)
) ENGINE=InnoDB;
