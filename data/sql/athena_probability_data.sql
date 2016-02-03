-- phpMyAdmin SQL Dump
-- version 4.1.12
-- http://www.phpmyadmin.net
--
-- Host: localhost:3306
-- Generation Time: Nov 26, 2015 at 03:09 PM
-- Server version: 5.6.16
-- PHP Version: 5.5.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `spam`
--

-- --------------------------------------------------------

--
-- Table structure for table `athena_probability`
--

CREATE TABLE IF NOT EXISTS `athena_probability` (
  `ap_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ap_variable` varbinary(255) NOT NULL DEFAULT '',
  `ap_variable_not` tinyint(1) NOT NULL DEFAULT '0',
  `ap_given` varbinary(255) DEFAULT '',
  `ap_given_not` tinyint(1) NOT NULL DEFAULT '0',
  `ap_value` double unsigned NOT NULL DEFAULT '0.01',
  `ap_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ap_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=73 ;

--
-- Dumping data for table `athena_probability`
--

INSERT INTO `athena_probability` (`ap_id`, `ap_variable`, `ap_variable_not`, `ap_given`, `ap_given_not`, `ap_value`, `ap_updated`) VALUES
(7, 'spam', 0, '', 0, 0.01, '2015-11-25 23:21:13'),
(8, 'spam', 1, '', 0, 0.99, '2015-11-25 23:21:13'),
(9, 'difflang', 0, '', 0, 0.005, '2015-11-25 23:21:50'),
(10, 'difflang', 1, '', 0, 0.995, '2015-11-25 23:21:50'),
(11, 'spam', 0, 'difflang', 0, 0.72, '2015-11-25 23:22:33'),
(12, 'spam', 0, 'difflang', 1, 0.006432161, '2015-11-25 23:22:33'),
(13, 'brokenspambot', 0, '', 0, 0.0001, '2015-11-26 00:09:12'),
(14, 'brokenspambot', 1, '', 0, 0.9999, '2015-11-26 00:09:12'),
(15, 'spam', 0, 'brokenspambot', 0, 0.99, '2015-11-26 00:09:12'),
(16, 'spam', 0, 'brokenspambot', 1, 0.00990199, '2015-11-26 00:09:12'),
(17, 'deleted', 0, '', 0, 0.001, '2015-11-26 00:25:50'),
(18, 'deleted', 1, '', 0, 0.999, '2015-11-26 00:25:50'),
(19, 'spam', 0, 'deleted', 0, 0.5, '2015-11-26 00:26:29'),
(20, 'spam', 0, 'deleted', 1, 0.00950951, '2015-11-26 00:26:29'),
(21, 'wanted', 0, '', 0, 0.75, '2015-11-26 00:41:36'),
(22, 'wanted', 1, '', 0, 0.25, '2015-11-26 00:41:36'),
(23, 'spam', 0, 'wanted', 0, 0.001333333, '2015-11-26 00:42:27'),
(24, 'spam', 0, 'wanted', 1, 0.036, '2015-11-26 00:42:27'),
(25, 'anon', 0, '', 0, 0.01, '2015-11-26 00:54:51'),
(26, 'user1', 0, '', 0, 0.01, '2015-11-26 00:54:51'),
(27, 'user5', 0, '', 0, 0.02, '2015-11-26 00:54:51'),
(28, 'user30', 0, '', 0, 0.02, '2015-11-26 00:54:51'),
(29, 'user60', 0, '', 0, 0.03, '2015-11-26 00:54:51'),
(30, 'user12', 0, '', 0, 0.04, '2015-11-26 00:54:51'),
(31, 'userother', 0, '', 0, 0.87, '2015-11-26 00:54:51'),
(34, 'spam', 0, 'anon', 0, 0.027, '2015-11-26 00:58:59'),
(35, 'spam', 0, 'user1', 0, 0.46, '2015-11-26 00:58:59'),
(36, 'spam', 0, 'user5', 0, 0.00005, '2015-11-26 00:58:59'),
(37, 'spam', 0, 'user30', 0, 0.05, '2015-11-26 00:58:59'),
(38, 'spam', 0, 'user60', 0, 0.013333333, '2015-11-26 00:58:59'),
(39, 'spam', 0, 'user12', 0, 0.044975, '2015-11-26 00:58:59'),
(40, 'spam', 0, 'user24', 0, 0.02, '2015-11-26 00:58:59'),
(41, 'spam', 0, 'userother', 0, 0.002298851, '2015-11-26 00:58:59'),
(42, 'user24', 0, '', 0, 0.01, '2015-11-26 01:00:16'),
(43, 'anon', 1, '', 0, 0.9, '2015-11-26 01:01:51'),
(44, 'spam', 0, 'anon', 1, 0.008111111, '2015-11-26 01:01:51'),
(45, 'titlelength', 0, '', 0, 0.2, '2015-11-26 01:37:05'),
(46, 'titlelength', 1, '', 0, 0.8, '2015-11-26 01:37:05'),
(47, 'spam', 0, 'titlelength', 0, 0.03, '2015-11-26 01:37:25'),
(48, 'spam', 0, 'titlelength', 1, 0.005, '2015-11-26 01:37:25'),
(49, 'nsmain', 0, '', 0, 0.6, '2015-11-26 01:49:22'),
(50, 'nstalk', 0, '', 0, 0.1, '2015-11-26 01:49:22'),
(51, 'nsuser', 0, '', 0, 0.1, '2015-11-26 01:49:40'),
(52, 'nsusertalk', 0, '', 0, 0.1, '2015-11-26 01:49:40'),
(53, 'nsother', 0, '', 0, 0.1, '2015-11-26 01:50:54'),
(54, 'spam', 0, 'nsmain', 0, 0.008333333, '2015-11-26 01:50:54'),
(55, 'spam', 0, 'nstalk', 0, 0.005, '2015-11-26 01:51:30'),
(56, 'spam', 0, 'nsuser', 0, 0.042, '2015-11-26 01:51:30'),
(57, 'spam', 0, 'nsusertalk', 0, 0.001, '2015-11-26 01:52:06'),
(58, 'spam', 0, 'nsother', 0, 0.02, '2015-11-26 01:52:06'),
(59, 'syntaxnone', 0, '', 0, 0.0999, '2015-11-26 13:30:12'),
(60, 'spam', 0, 'syntaxnone', 0, 0.016026026, '2015-11-26 13:30:12'),
(61, 'syntaxbasic', 0, '', 0, 0.1, '2015-11-26 13:30:40'),
(62, 'spam', 0, 'syntaxbasic', 0, 0.082, '2015-11-26 13:30:40'),
(63, 'syntaxcomplex', 0, '', 0, 0.8, '2015-11-26 13:31:20'),
(64, 'spam', 0, 'syntaxcomplex', 0, 0.000125, '2015-11-26 13:31:20'),
(65, 'links0', 0, '', 0, 0.1, '2015-11-26 14:01:38'),
(66, 'spam', 0, 'links0', 0, 0.019, '2015-11-26 14:01:38'),
(67, 'links5', 0, '', 0, 0.8, '2015-11-26 14:02:02'),
(68, 'spam', 0, 'links5', 0, 0.005, '2015-11-26 14:02:02'),
(69, 'links20', 0, '', 0, 0.099, '2015-11-26 14:04:03'),
(70, 'spam', 0, 'links20', 0, 0.041313131, '2015-11-26 14:04:03'),
(71, 'links50', 0, '', 0, 0.001, '2015-11-26 14:04:32'),
(72, 'spam', 0, 'links50', 0, 0.01, '2015-11-26 14:04:32');

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
