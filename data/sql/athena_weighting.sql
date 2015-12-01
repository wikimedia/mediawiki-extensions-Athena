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
-- Table structure for table `athena_weighting`
--

CREATE TABLE IF NOT EXISTS `athena_weighting` (
  `aw_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `aw_variable` varbinary(255) NOT NULL DEFAULT '',
  `aw_value` double unsigned NOT NULL DEFAULT '0.1',
  `aw_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`aw_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=10 ;

--
-- Dumping data for table `athena_weighting`
--

INSERT INTO `athena_weighting` (`aw_id`, `aw_variable`, `aw_value`, `aw_updated`) VALUES
(1, 'difflang', 0.2, '2015-11-25 23:30:49'),
(2, 'userage', 0.25, '2015-11-26 00:03:37'),
(3, 'links', 0.2, '2015-11-26 00:03:37'),
(4, 'syntax', 0.3, '2015-11-26 00:03:37'),
(5, 'titlelength', 0.0125, '2015-11-26 00:03:37'),
(6, 'namespace', 0.0125, '2015-11-26 00:03:37'),
(7, 'wanted', 0.0125, '2015-11-26 00:03:37'),
(8, 'deleted', 0.0125, '2015-11-26 00:03:37'),
(9, 'brokenspambot', 0, '2015-11-26 00:05:01');

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
