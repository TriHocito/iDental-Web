-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1:3306
-- Thời gian đã tạo: Th1 04, 2026 lúc 11:10 AM
-- Phiên bản máy phục vụ: 9.1.0
-- Phiên bản PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `nha_khoa`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `bacsi`
--

DROP TABLE IF EXISTS `bacsi`;
CREATE TABLE IF NOT EXISTS `bacsi` (
  `id_bacsi` int NOT NULL AUTO_INCREMENT,
  `sdt` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mat_khau_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ten_day_du` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chuyen_khoa` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `link_anh_dai_dien` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_quantrivien_tao` int NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trang_thai` tinyint(1) DEFAULT '1' COMMENT '1: Hoạt động, 0: Bị khóa',
  PRIMARY KEY (`id_bacsi`),
  UNIQUE KEY `sdt` (`sdt`),
  UNIQUE KEY `email` (`email`),
  KEY `id_quantrivien_tao` (`id_quantrivien_tao`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `bacsi`
--

INSERT INTO `bacsi` (`id_bacsi`, `sdt`, `mat_khau_hash`, `ten_day_du`, `chuyen_khoa`, `link_anh_dai_dien`, `id_quantrivien_tao`, `email`, `trang_thai`) VALUES
(1, '111', '$2y$12$bikVSEy.7O3H8ziAAndnY.iyYRhkpeg1qrvSW0zC5ZHIeZy9iqZ1q', 'Nam', 'răng', '../assets/img/doc_1_1767420235.jpg', 1, 'tranhaitrivn@gmail.com', 1),
(2, '112', '$2y$12$Wy68y6NiaH/GUO3H63JBT.wWgfshCuMOuKCc3ddw0UT39doi8SAWO', 'tuấn', 'mặt', NULL, 1, 'tranhaidinh1@gmail.com', 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `benhan`
--

DROP TABLE IF EXISTS `benhan`;
CREATE TABLE IF NOT EXISTS `benhan` (
  `id_benhan` int NOT NULL AUTO_INCREMENT,
  `id_lichhen` int NOT NULL COMMENT 'Quan hệ 1:1 với Lịch hẹn',
  `chan_doan` text COLLATE utf8mb4_unicode_ci,
  `ghi_chu_bac_si` text COLLATE utf8mb4_unicode_ci,
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_benhan`),
  UNIQUE KEY `id_lichhen` (`id_lichhen`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `benhan`
--

INSERT INTO `benhan` (`id_benhan`, `id_lichhen`, `chan_doan`, `ghi_chu_bac_si`, `ngay_tao`) VALUES
(1, 6, 'Sâu nhiều, nên đi khám định kì', 'mua kem đánh răng, đánh thường xuyên', '2026-01-04 02:24:11'),
(2, 9, 'gãy rằng', 'trồng răng', '2026-01-04 02:39:08');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `benhnhan`
--

DROP TABLE IF EXISTS `benhnhan`;
CREATE TABLE IF NOT EXISTS `benhnhan` (
  `id_benhnhan` int NOT NULL AUTO_INCREMENT,
  `sdt` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mat_khau_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ten_day_du` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_quantrivien_tao` int DEFAULT NULL,
  PRIMARY KEY (`id_benhnhan`),
  UNIQUE KEY `sdt` (`sdt`),
  UNIQUE KEY `email` (`email`),
  KEY `id_quantrivien_tao` (`id_quantrivien_tao`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `benhnhan`
--

INSERT INTO `benhnhan` (`id_benhnhan`, `sdt`, `mat_khau_hash`, `ten_day_du`, `email`, `id_quantrivien_tao`) VALUES
(1, '0912572871', '$2y$12$7DoX9AHnkIxCQcZN01tNdOdJYhRcUeB.ZulhEvSupmtSAVUTKyUPO', 'phương', 'tranhaitrivn@gmail.com', NULL),
(2, '0388200877', '$2y$12$auU1w/7/80c/82ikncXsuurZafiQfEfpFatdTp1n1LiVe8rvxXScS', 'phương', 'gialinhpham2806@gmail.com', NULL),
(3, '0943857924', '$2y$12$nc3K8OVbpw0G449aOZwBIO1vxEEmwiMqa.MPR5QTyon4hWiSDvjZ2', 'sang', 'wmh57876@laoia.com', NULL),
(4, '0827075563', '$2y$12$C8ooW8gpz2JvAcMFaeUU3e9zkUsUdsFCGW5rA7K1tbbfRKMFk/JzO', 'vinh', 'isi37550@laoia.com', NULL),
(5, '0544238876', '$2y$12$f1sgVt/bLM49bXspFS7kkOUWZD9n6umaH7eIyfu5srP9y7Qcow8be', 'khoa', 'bqr73358@laoia.com', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `dichvu`
--

DROP TABLE IF EXISTS `dichvu`;
CREATE TABLE IF NOT EXISTS `dichvu` (
  `id_dichvu` int NOT NULL AUTO_INCREMENT,
  `ten_dich_vu` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mo_ta` text COLLATE utf8mb4_unicode_ci,
  `gia_tien` decimal(10,2) NOT NULL DEFAULT '0.00',
  `thoi_gian_phut` int NOT NULL COMMENT 'Thời gian khám dự kiến',
  PRIMARY KEY (`id_dichvu`),
  UNIQUE KEY `ten_dich_vu` (`ten_dich_vu`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `dichvu`
--

INSERT INTO `dichvu` (`id_dichvu`, `ten_dich_vu`, `mo_ta`, `gia_tien`, `thoi_gian_phut`) VALUES
(1, 'Khám tổng quát', 'Kiểm tra và tư vấn sức khỏe răng miệng định kỳ.', 0.00, 15),
(2, 'Cạo vôi răng', 'Làm sạch mảng bám, vôi răng bằng máy siêu âm.', 300000.00, 30),
(3, 'Nhổ răng sữa', 'Nhổ răng sữa cho trẻ em nhẹ nhàng, không đau.', 100000.00, 20),
(5, 'Tẩy trắng răng', 'Tẩy trắng răng công nghệ Laser Whitening.', 1200000.00, 60),
(6, 'Trám răng thẩm mỹ', 'Hàn trám răng sâu, răng sứt mẻ bằng Composite.', 400000.00, 45),
(7, 'Điều trị tủy', 'Chữa tủy răng cửa hoặc răng hàm (chưa bao gồm bọc sứ).', 800000.00, 90),
(8, 'Niềng răng mắc cài kim loại', 'Chỉnh nha cố định bằng mắ', 25000000.00, 120),
(9, 'Bọc răng sứ Tit', 'Phục hình răng sứ sườn kim loại Titan.', 2000000.00, 60),
(16, 'khám răng sữa', '', 100000.00, 30);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `giuongbenh`
--

DROP TABLE IF EXISTS `giuongbenh`;
CREATE TABLE IF NOT EXISTS `giuongbenh` (
  `id_giuongbenh` int NOT NULL AUTO_INCREMENT,
  `ten_giuong` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Số giường hoặc tên giường',
  PRIMARY KEY (`id_giuongbenh`),
  UNIQUE KEY `ten_giuong` (`ten_giuong`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `giuongbenh`
--

INSERT INTO `giuongbenh` (`id_giuongbenh`, `ten_giuong`) VALUES
(1, 'Giường số 1'),
(2, 'Giường số 2'),
(4, 'Giường số 3'),
(5, 'Giường số 4');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lichhen`
--

DROP TABLE IF EXISTS `lichhen`;
CREATE TABLE IF NOT EXISTS `lichhen` (
  `id_lichhen` int NOT NULL AUTO_INCREMENT,
  `id_benhnhan` int NOT NULL,
  `id_bacsi` int NOT NULL,
  `id_dichvu` int NOT NULL,
  `ngay_gio_hen` datetime NOT NULL,
  `trang_thai` enum('cho_xac_nhan','da_xac_nhan','dang_kham','hoan_thanh','huy') COLLATE utf8mb4_unicode_ci DEFAULT 'cho_xac_nhan',
  `nguoi_tao_lich` enum('benh_nhan','bac_si','quan_tri_vien') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'benh_nhan',
  `so_tien_thanh_toan` decimal(10,2) DEFAULT NULL,
  `ghi_chu` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id_lichhen`),
  KEY `id_benhnhan` (`id_benhnhan`),
  KEY `id_bacsi` (`id_bacsi`),
  KEY `id_dichvu` (`id_dichvu`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `lichhen`
--

INSERT INTO `lichhen` (`id_lichhen`, `id_benhnhan`, `id_bacsi`, `id_dichvu`, `ngay_gio_hen`, `trang_thai`, `nguoi_tao_lich`, `so_tien_thanh_toan`, `ghi_chu`) VALUES
(1, 1, 1, 5, '2026-01-06 08:00:00', 'huy', 'benh_nhan', NULL, NULL),
(2, 2, 1, 7, '2026-01-06 08:00:00', 'da_xac_nhan', 'benh_nhan', NULL, NULL),
(3, 1, 1, 3, '2026-01-06 10:30:00', 'da_xac_nhan', 'benh_nhan', NULL, NULL),
(4, 3, 1, 3, '2026-01-15 13:00:00', 'da_xac_nhan', 'benh_nhan', NULL, NULL),
(5, 4, 1, 3, '2026-01-16 08:00:00', 'da_xac_nhan', 'benh_nhan', NULL, NULL),
(6, 5, 2, 3, '2026-01-14 13:00:00', 'hoan_thanh', 'benh_nhan', NULL, NULL),
(7, 5, 1, 5, '2026-01-14 13:20:00', 'huy', 'benh_nhan', NULL, NULL),
(8, 1, 2, 3, '2026-01-15 13:00:00', 'huy', 'benh_nhan', NULL, NULL),
(9, 1, 2, 5, '2026-01-15 13:00:00', 'hoan_thanh', 'benh_nhan', NULL, ' | Đổi lịch: bệnh nhân yêu cầu đổi'),
(10, 1, 1, 3, '2026-01-20 08:00:00', 'huy', 'benh_nhan', NULL, NULL),
(11, 1, 2, 6, '2026-01-20 08:20:00', 'da_xac_nhan', 'benh_nhan', NULL, NULL),
(12, 1, 1, 9, '2026-01-20 09:05:00', 'da_xac_nhan', 'benh_nhan', NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lichlamviec`
--

DROP TABLE IF EXISTS `lichlamviec`;
CREATE TABLE IF NOT EXISTS `lichlamviec` (
  `id_lichlamviec` int NOT NULL AUTO_INCREMENT,
  `id_bacsi` int NOT NULL,
  `id_giuongbenh` int NOT NULL COMMENT 'Ghế sử dụng trong ca này',
  `id_quantrivien_tao` int NOT NULL,
  `ngay_trong_tuan` tinyint NOT NULL COMMENT '1=Thứ 2, ..., 7=CN',
  `gio_bat_dau` time NOT NULL,
  `gio_ket_thuc` time NOT NULL,
  `ngay_hieu_luc` date NOT NULL,
  `ngay_het_han` date DEFAULT NULL,
  PRIMARY KEY (`id_lichlamviec`),
  KEY `id_bacsi` (`id_bacsi`),
  KEY `id_quantrivien_tao` (`id_quantrivien_tao`),
  KEY `fk_lichlamviec_giuong` (`id_giuongbenh`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `lichlamviec`
--

INSERT INTO `lichlamviec` (`id_lichlamviec`, `id_bacsi`, `id_giuongbenh`, `id_quantrivien_tao`, `ngay_trong_tuan`, `gio_bat_dau`, `gio_ket_thuc`, `ngay_hieu_luc`, `ngay_het_han`) VALUES
(1, 1, 1, 1, 1, '08:00:00', '12:00:00', '2026-01-05', NULL),
(2, 1, 1, 1, 2, '08:00:00', '12:00:00', '2026-01-06', NULL),
(3, 1, 1, 1, 3, '08:00:00', '12:00:00', '2026-01-07', NULL),
(4, 1, 1, 1, 3, '13:00:00', '17:00:00', '2026-01-07', NULL),
(5, 1, 1, 1, 4, '13:00:00', '17:00:00', '2026-01-08', NULL),
(6, 1, 1, 1, 5, '13:00:00', '17:00:00', '2026-01-09', NULL),
(7, 1, 1, 1, 6, '13:00:00', '17:00:00', '2026-01-10', NULL),
(8, 1, 1, 1, 4, '13:00:00', '17:00:00', '2026-01-15', NULL),
(9, 1, 1, 1, 5, '08:00:00', '12:00:00', '2026-01-16', NULL),
(10, 1, 1, 1, 3, '13:00:00', '17:00:00', '2026-01-14', NULL),
(11, 2, 2, 1, 3, '13:00:00', '17:00:00', '2026-01-14', NULL),
(12, 2, 2, 1, 1, '08:00:00', '12:00:00', '2026-01-19', NULL),
(13, 2, 2, 1, 2, '08:00:00', '12:00:00', '2026-01-20', NULL),
(14, 2, 2, 1, 3, '08:00:00', '12:00:00', '2026-01-21', NULL),
(15, 2, 2, 1, 4, '08:00:00', '12:00:00', '2026-01-22', NULL),
(16, 2, 2, 1, 5, '08:00:00', '12:00:00', '2026-01-23', NULL),
(17, 2, 2, 1, 6, '08:00:00', '12:00:00', '2026-01-24', NULL),
(18, 1, 2, 1, 1, '08:00:00', '12:00:00', '2026-01-12', NULL),
(19, 1, 2, 1, 2, '08:00:00', '12:00:00', '2026-01-13', NULL),
(20, 1, 2, 1, 3, '08:00:00', '12:00:00', '2026-01-14', NULL),
(21, 1, 2, 1, 4, '08:00:00', '12:00:00', '2026-01-15', NULL),
(22, 1, 2, 1, 6, '08:00:00', '12:00:00', '2026-01-17', NULL),
(23, 2, 1, 1, 1, '13:00:00', '17:00:00', '2026-01-12', NULL),
(24, 2, 1, 1, 2, '13:00:00', '17:00:00', '2026-01-13', NULL),
(25, 2, 2, 1, 4, '13:00:00', '17:00:00', '2026-01-15', NULL),
(26, 2, 1, 1, 5, '13:00:00', '17:00:00', '2026-01-16', NULL),
(27, 2, 1, 1, 6, '13:00:00', '17:00:00', '2026-01-17', NULL),
(28, 1, 1, 1, 2, '08:00:00', '12:00:00', '2026-01-20', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `quantrivien`
--

DROP TABLE IF EXISTS `quantrivien`;
CREATE TABLE IF NOT EXISTS `quantrivien` (
  `id_quantrivien` int NOT NULL AUTO_INCREMENT,
  `ten_dang_nhap` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mat_khau_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ten_day_du` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `id_quantrivien_tao` int DEFAULT NULL,
  PRIMARY KEY (`id_quantrivien`),
  UNIQUE KEY `ten_dang_nhap` (`ten_dang_nhap`),
  KEY `id_quantrivien_tao` (`id_quantrivien_tao`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `quantrivien`
--

INSERT INTO `quantrivien` (`id_quantrivien`, `ten_dang_nhap`, `mat_khau_hash`, `ten_day_du`, `ngay_tao`, `id_quantrivien_tao`) VALUES
(1, 'admin', '$2y$12$LfkJJN0z174ACYw1DAu8O.V1HdlcSAfYy.JdrHtr7xbdg.Ng66bze', 'Admin Hệ Thống', '2025-11-20 13:01:35', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `yeucaunghi`
--

DROP TABLE IF EXISTS `yeucaunghi`;
CREATE TABLE IF NOT EXISTS `yeucaunghi` (
  `id_yeucau` int NOT NULL AUTO_INCREMENT,
  `id_bacsi` int NOT NULL COMMENT 'Bác sĩ xin nghỉ',
  `ngay_nghi` date NOT NULL,
  `ca_nghi` enum('Sang','Chieu') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ly_do` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `trang_thai` enum('cho_duyet','da_duyet','tu_choi') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'cho_duyet',
  `id_quantrivien_duyet` int DEFAULT NULL COMMENT 'Admin đã xử lý',
  `ngay_tao` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_yeucau`),
  KEY `id_bacsi` (`id_bacsi`),
  KEY `id_quantrivien_duyet` (`id_quantrivien_duyet`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `yeucaunghi`
--

INSERT INTO `yeucaunghi` (`id_yeucau`, `id_bacsi`, `ngay_nghi`, `ca_nghi`, `ly_do`, `trang_thai`, `id_quantrivien_duyet`, `ngay_tao`) VALUES
(1, 1, '2026-01-05', 'Sang', 'xe hư', 'da_duyet', 1, '2026-01-03 07:38:47'),
(2, 1, '2026-01-14', 'Chieu', 'bận', 'da_duyet', 1, '2026-01-03 08:38:16'),
(3, 1, '2026-01-20', 'Sang', 'bệnh', 'da_duyet', 1, '2026-01-04 09:42:03'),
(4, 1, '2026-01-06', 'Sang', 'bận', 'da_duyet', 1, '2026-01-04 09:53:02');

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `bacsi`
--
ALTER TABLE `bacsi`
  ADD CONSTRAINT `bacsi_ibfk_1` FOREIGN KEY (`id_quantrivien_tao`) REFERENCES `quantrivien` (`id_quantrivien`);

--
-- Các ràng buộc cho bảng `benhan`
--
ALTER TABLE `benhan`
  ADD CONSTRAINT `benhan_ibfk_1` FOREIGN KEY (`id_lichhen`) REFERENCES `lichhen` (`id_lichhen`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `benhnhan`
--
ALTER TABLE `benhnhan`
  ADD CONSTRAINT `benhnhan_ibfk_1` FOREIGN KEY (`id_quantrivien_tao`) REFERENCES `quantrivien` (`id_quantrivien`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `lichhen`
--
ALTER TABLE `lichhen`
  ADD CONSTRAINT `lichhen_ibfk_1` FOREIGN KEY (`id_benhnhan`) REFERENCES `benhnhan` (`id_benhnhan`) ON DELETE CASCADE,
  ADD CONSTRAINT `lichhen_ibfk_2` FOREIGN KEY (`id_bacsi`) REFERENCES `bacsi` (`id_bacsi`),
  ADD CONSTRAINT `lichhen_ibfk_3` FOREIGN KEY (`id_dichvu`) REFERENCES `dichvu` (`id_dichvu`);

--
-- Các ràng buộc cho bảng `lichlamviec`
--
ALTER TABLE `lichlamviec`
  ADD CONSTRAINT `fk_lichlamviec_giuong` FOREIGN KEY (`id_giuongbenh`) REFERENCES `giuongbenh` (`id_giuongbenh`) ON DELETE CASCADE,
  ADD CONSTRAINT `lichlamviec_ibfk_1` FOREIGN KEY (`id_bacsi`) REFERENCES `bacsi` (`id_bacsi`) ON DELETE CASCADE,
  ADD CONSTRAINT `lichlamviec_ibfk_2` FOREIGN KEY (`id_quantrivien_tao`) REFERENCES `quantrivien` (`id_quantrivien`);

--
-- Các ràng buộc cho bảng `quantrivien`
--
ALTER TABLE `quantrivien`
  ADD CONSTRAINT `quantrivien_ibfk_1` FOREIGN KEY (`id_quantrivien_tao`) REFERENCES `quantrivien` (`id_quantrivien`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `yeucaunghi`
--
ALTER TABLE `yeucaunghi`
  ADD CONSTRAINT `yeucau_ibfk_1` FOREIGN KEY (`id_bacsi`) REFERENCES `bacsi` (`id_bacsi`) ON DELETE CASCADE,
  ADD CONSTRAINT `yeucau_ibfk_2` FOREIGN KEY (`id_quantrivien_duyet`) REFERENCES `quantrivien` (`id_quantrivien`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
