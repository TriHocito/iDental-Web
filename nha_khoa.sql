-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1:3306
-- Thời gian đã tạo: Th12 10, 2025 lúc 02:53 PM
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
  PRIMARY KEY (`id_bacsi`),
  UNIQUE KEY `sdt` (`sdt`),
  KEY `id_quantrivien_tao` (`id_quantrivien_tao`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `bacsi`
--

INSERT INTO `bacsi` (`id_bacsi`, `sdt`, `mat_khau_hash`, `ten_day_du`, `chuyen_khoa`, `link_anh_dai_dien`, `id_quantrivien_tao`) VALUES
(2, '114', '$2y$12$afazB9xjxl1cwxlQ135Xmu..5RDY5rD396.cQeXw5wRjhcQIZVUei', 'Minh Phương', 'Nha khoa Tổng quát', NULL, 1),
(3, '112', '$2y$12$ib5TJm/YxEaRhiB6y2uT7ufKBKS/FBTBPWgPCjfdTL8DBNXyvxxyS', 'Trần Hải Trí', 'Phục hình răng sứ', '../assets/img/doc_3_1764992177.jpg', 1),
(4, '115', '$2y$12$Y74ZtV3fGf7ljHdsTQC3kuNwlZfOzF1V/aGHUGSQT7uGdcs7bK.qS', 'Võ Hoàng Yến', 'Răng', '../assets/img/doc_4_1765351180.jpeg', 1);

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `benhan`
--

INSERT INTO `benhan` (`id_benhan`, `id_lichhen`, `chan_doan`, `ghi_chu_bac_si`, `ngay_tao`) VALUES
(1, 1, 'Đang niềng 2 tuần nữa tháo', '1 tuần sau tài khám', '2025-11-22 03:39:37'),
(2, 8, 'Viêm nướu nhẹ', 'Đã lấy vôi răng, dặn bệnh nhân súc miệng nước muối.', '2025-11-27 05:21:39'),
(3, 9, 'Sâu răng R36', 'Đã trám composite, theo dõi 6 tháng.', '2025-11-27 05:21:39'),
(4, 13, 'Đã khám ', 'Mua thuốc theo đơn', '2025-12-07 10:43:19'),
(5, 15, 'binh thuong', 'khong can di kham lan sau', '2025-12-08 00:14:06');

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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `benhnhan`
--

INSERT INTO `benhnhan` (`id_benhnhan`, `sdt`, `mat_khau_hash`, `ten_day_du`, `email`, `id_quantrivien_tao`) VALUES
(1, '0912572871', '$2y$12$1T7gTEYlELP1yGt8MlJreO4SEd2mebPOqORmwY4BViPbPJ7/OksLG', 'Trần Hải Trí', 'tranhaitrivn@gmail.com', NULL),
(4, '0707189144', '$2y$12$kozJnwFR9NYCJiE7PqQaD.GluC31nO1AF44ZSzTDSdv602WZdY65e', 'Nguyễn Quang Vinh', 'tranhaidinh1@gmail.com', NULL),
(9, '0821075563', '$2y$12$7KJXHVEHq3f7u8uJOJZpiOnOcd4/g3z2y3Q0gXb5AITv6D2sorYkq', 'Nguyen Van Long', 'gialinhpham2806@gmail.com', NULL),
(10, '0333', '$2y$12$t0t4CV0nuob.hEsmZ3M.PeVEpbq6aEtN2BXes78XnTLzVp0IgbmHy', 'Văn Nam', 'dh52201638@student.stu.edu.vn', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(9, 'Bọc răng sứ Titan', 'Phục hình răng sứ sườn kim loại Titan.', 2000000.00, 60),
(13, 'Khám nướu', 'khám nướu nho', 1000000.00, 15);

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `giuongbenh`
--

INSERT INTO `giuongbenh` (`id_giuongbenh`, `ten_giuong`) VALUES
(1, 'Giường số 1'),
(2, 'Giường số 2');

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
  PRIMARY KEY (`id_lichhen`),
  KEY `id_benhnhan` (`id_benhnhan`),
  KEY `id_bacsi` (`id_bacsi`),
  KEY `id_dichvu` (`id_dichvu`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `lichhen`
--

INSERT INTO `lichhen` (`id_lichhen`, `id_benhnhan`, `id_bacsi`, `id_dichvu`, `ngay_gio_hen`, `trang_thai`, `nguoi_tao_lich`, `so_tien_thanh_toan`) VALUES
(1, 4, 2, 2, '2025-11-22 09:26:00', 'hoan_thanh', 'benh_nhan', NULL),
(2, 1, 3, 1, '2025-11-22 13:00:00', 'da_xac_nhan', 'benh_nhan', NULL),
(3, 1, 2, 8, '2025-11-29 08:00:00', 'huy', 'benh_nhan', NULL),
(5, 1, 2, 5, '2025-11-25 08:00:00', 'huy', 'benh_nhan', NULL),
(7, 4, 2, 3, '2025-11-25 21:40:00', 'da_xac_nhan', 'bac_si', NULL),
(8, 1, 2, 1, '2025-11-17 12:21:39', 'hoan_thanh', 'benh_nhan', NULL),
(9, 1, 3, 2, '2025-10-27 12:21:39', 'hoan_thanh', 'bac_si', NULL),
(10, 1, 2, 3, '2025-11-29 12:21:39', 'da_xac_nhan', 'benh_nhan', NULL),
(11, 1, 3, 1, '2025-12-02 12:21:39', 'huy', 'benh_nhan', NULL),
(12, 1, 2, 2, '2025-11-28 12:21:39', 'huy', 'quan_tri_vien', NULL),
(13, 9, 3, 1, '2025-12-11 08:00:00', 'hoan_thanh', 'benh_nhan', NULL),
(14, 1, 2, 2, '2025-12-12 13:00:00', 'huy', 'benh_nhan', NULL),
(15, 1, 2, 5, '2025-12-12 13:20:00', 'hoan_thanh', 'benh_nhan', NULL),
(16, 1, 2, 2, '2025-12-11 13:00:00', 'huy', 'benh_nhan', NULL),
(17, 1, 2, 6, '2025-12-09 13:00:00', 'da_xac_nhan', 'benh_nhan', NULL),
(18, 4, 2, 7, '2025-12-15 20:55:00', 'da_xac_nhan', 'quan_tri_vien', NULL),
(19, 1, 2, 5, '2025-12-09 13:45:00', 'da_xac_nhan', 'quan_tri_vien', NULL),
(20, 10, 3, 2, '2025-12-08 13:00:00', 'da_xac_nhan', 'quan_tri_vien', NULL),
(21, 1, 3, 2, '2025-12-19 13:00:00', 'da_xac_nhan', 'benh_nhan', NULL),
(22, 1, 4, 3, '2025-12-12 08:00:00', '', 'benh_nhan', NULL),
(23, 1, 4, 3, '2025-12-18 08:00:00', '', 'benh_nhan', NULL),
(24, 1, 4, 2, '2025-12-25 08:00:00', 'da_xac_nhan', 'benh_nhan', NULL),
(25, 9, 4, 7, '2025-12-18 20:23:00', 'huy', 'bac_si', NULL),
(26, 1, 3, 5, '2025-12-19 08:00:00', 'da_xac_nhan', 'benh_nhan', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `lichlamviec`
--

INSERT INTO `lichlamviec` (`id_lichlamviec`, `id_bacsi`, `id_giuongbenh`, `id_quantrivien_tao`, `ngay_trong_tuan`, `gio_bat_dau`, `gio_ket_thuc`, `ngay_hieu_luc`, `ngay_het_han`) VALUES
(1, 3, 1, 1, 1, '08:00:00', '12:00:00', '2025-12-08', NULL),
(2, 3, 1, 1, 2, '08:00:00', '12:00:00', '2025-12-09', NULL),
(3, 3, 1, 1, 3, '08:00:00', '12:00:00', '2025-12-10', NULL),
(4, 3, 1, 1, 4, '08:00:00', '12:00:00', '2025-12-11', NULL),
(5, 3, 1, 1, 5, '08:00:00', '12:00:00', '2025-12-12', NULL),
(6, 2, 2, 1, 1, '13:00:00', '17:00:00', '2025-12-08', NULL),
(7, 2, 2, 1, 2, '13:00:00', '17:00:00', '2025-12-09', NULL),
(8, 2, 2, 1, 3, '13:00:00', '17:00:00', '2025-12-10', NULL),
(9, 2, 2, 1, 4, '13:00:00', '17:00:00', '2025-12-11', NULL),
(10, 2, 2, 1, 5, '13:00:00', '17:00:00', '2025-12-12', NULL),
(11, 3, 1, 1, 7, '13:00:00', '17:00:00', '2025-12-07', NULL),
(12, 2, 1, 1, 1, '13:00:00', '17:00:00', '2025-12-15', NULL),
(13, 3, 1, 1, 1, '13:00:00', '17:00:00', '2025-12-15', NULL),
(14, 3, 1, 1, 2, '13:00:00', '17:00:00', '2025-12-16', NULL),
(15, 3, 1, 1, 3, '13:00:00', '17:00:00', '2025-12-17', NULL),
(16, 3, 1, 1, 4, '13:00:00', '17:00:00', '2025-12-18', NULL),
(17, 3, 1, 1, 5, '13:00:00', '17:00:00', '2025-12-19', NULL),
(18, 3, 1, 1, 6, '13:00:00', '17:00:00', '2025-12-20', NULL),
(19, 3, 1, 1, 1, '13:00:00', '17:00:00', '2025-12-08', NULL),
(20, 4, 1, 1, 4, '08:00:00', '12:00:00', '2025-12-25', NULL),
(21, 3, 1, 1, 5, '08:00:00', '12:00:00', '2025-12-19', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `quantrivien`
--

INSERT INTO `quantrivien` (`id_quantrivien`, `ten_dang_nhap`, `mat_khau_hash`, `ten_day_du`, `ngay_tao`, `id_quantrivien_tao`) VALUES
(1, 'admin', '$2y$12$gUxtW4fNK08sFLE4lepWHeDLorq7enqaB83dMWn9icK2nO9mLU/oS', 'Admin Hệ Thống', '2025-11-20 13:01:35', NULL),
(4, 'tuansang', '$2y$12$uInngEtmVAXRwQBzeqmqq.qGdpL0ktQv/vOP95HzhRchku/d6ka42', 'Trần Sang', '2025-12-08 12:27:45', 1);

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `yeucaunghi`
--

INSERT INTO `yeucaunghi` (`id_yeucau`, `id_bacsi`, `ngay_nghi`, `ca_nghi`, `ly_do`, `trang_thai`, `id_quantrivien_duyet`, `ngay_tao`) VALUES
(1, 2, '2025-12-10', 'Chieu', 'đi khám bệnh', 'da_duyet', 1, '2025-12-07 13:00:20'),
(2, 2, '2025-12-11', 'Chieu', 'ban viec gia dinh', 'da_duyet', 1, '2025-12-08 00:15:19'),
(3, 3, '2025-12-15', 'Chieu', 'bận', 'da_duyet', 1, '2025-12-09 05:57:56');

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
