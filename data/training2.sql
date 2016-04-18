-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Mar 08, 2016 at 11:52 AM
-- Server version: 10.1.9-MariaDB
-- PHP Version: 7.0.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `training3`
--

-- --------------------------------------------------------

--
-- Table structure for table `athena_stats`
--

CREATE TABLE `athena_stats` (
  `as_id` int(10) UNSIGNED NOT NULL,
  `as_name` varbinary(255) NOT NULL DEFAULT '',
  `as_value` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `as_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `athena_stats`
--

INSERT INTO `athena_stats` (`as_id`, `as_name`, `as_value`, `as_updated`) VALUES
(1, 'pages', 3510, '2016-02-28 17:44:56'),
(2, 'spam', 236, '2016-02-29 21:08:58'),
(3, 'notspam', 3274, '2016-02-29 21:09:02'),
(4, 'difflang', 398, '0000-00-00 00:00:00'),
(5, 'samelang', 3112, '0000-00-00 00:00:00'),
(6, 'deleted', 3, '2016-02-28 15:19:05'),
(7, 'notdeleted', 3507, '2016-02-28 17:44:56'),
(8, 'wanted', 15, '2016-02-28 17:20:34'),
(9, 'notwanted', 3495, '2016-02-28 17:44:56'),
(10, 'userother', 2701, '0000-00-00 00:00:00'),
(11, 'anon', 226, '0000-00-00 00:00:00'),
(12, 'user1', 16, '0000-00-00 00:00:00'),
(13, 'user5', 78, '0000-00-00 00:00:00'),
(14, 'user30', 169, '0000-00-00 00:00:00'),
(15, 'user60', 80, '0000-00-00 00:00:00'),
(16, 'user12', 180, '0000-00-00 00:00:00'),
(17, 'user24', 60, '0000-00-00 00:00:00'),
(18, 'titlelength', 522, '2016-02-28 17:44:56'),
(19, 'nottitlelength', 2988, '2016-02-28 17:44:35'),
(20, 'nsmain', 424, '2016-02-28 17:44:56'),
(21, 'nstalk', 97, '2016-02-28 17:40:43'),
(22, 'nsuser', 124, '2016-02-28 17:44:35'),
(23, 'nsusertalk', 16, '2016-02-28 17:20:34'),
(24, 'nsother', 58, '2016-02-28 17:15:13'),
(25, 'syntaxnone', 2492, '0000-00-00 00:00:00'),
(26, 'syntaxbasic', 804, '0000-00-00 00:00:00'),
(27, 'syntaxcomplex', 213, '0000-00-00 00:00:00'),
(28, 'brokenspambot', 1, '0000-00-00 00:00:00'),
(29, 'links0', 967, '2016-02-28 17:40:43'),
(30, 'links5', 94, '2016-02-28 17:44:56'),
(31, 'links20', 44, '2016-02-28 17:44:35'),
(32, 'links50', 2405, '2016-02-28 17:44:04'),
(33, 'spamanddifflang', 24, '2016-02-29 21:08:58'),
(34, 'spamandsamelang', 212, '2016-02-29 21:06:55'),
(35, 'spamanddeleted', 3, '2016-02-28 21:54:42'),
(36, 'spamandnotdeleted', 233, '2016-02-29 21:08:58'),
(37, 'spamandwanted', 1, '2016-02-28 22:04:02'),
(38, 'spamandnotwanted', 235, '2016-02-29 21:08:58'),
(39, 'spamanduserother', 4, '0000-00-00 00:00:00'),
(40, 'spamandanon', 173, '0000-00-00 00:00:00'),
(41, 'spamanduser1', 12, '0000-00-00 00:00:00'),
(42, 'spamanduser5', 10, '0000-00-00 00:00:00'),
(43, 'spamanduser30', 10, '0000-00-00 00:00:00'),
(44, 'spamanduser60', 3, '0000-00-00 00:00:00'),
(45, 'spamanduser12', 20, '0000-00-00 00:00:00'),
(46, 'spamanduser24', 4, '0000-00-00 00:00:00'),
(47, 'spamandtitlelength', 162, '2016-02-29 21:06:41'),
(48, 'spamandnottitlelength', 74, '2016-02-29 21:08:58'),
(49, 'spamandnsmain', 192, '2016-02-29 21:06:55'),
(50, 'spamandnstalk', 6, '2016-02-29 21:08:58'),
(51, 'spamandnsuser', 33, '2016-02-29 21:06:08'),
(52, 'spamandnsusertalk', 0, '2016-02-28 12:18:07'),
(53, 'spamandnsother', 5, '2016-02-28 22:04:54'),
(54, 'spamandsyntaxnone', 1, '0000-00-00 00:00:00'),
(55, 'spamandsyntaxbasic', 233, '0000-00-00 00:00:00'),
(56, 'spamandsyntaxcomplex', 1, '0000-00-00 00:00:00'),
(57, 'spamandbrokenspambot', 1, '0000-00-00 00:00:00'),
(58, 'spamandlinks0', 138, '2016-02-29 21:08:58'),
(59, 'spamandlinks5', 41, '2016-02-29 21:06:19'),
(60, 'spamandlinks20', 28, '2016-02-29 21:06:12'),
(61, 'spamandlinks50', 29, '2016-02-28 22:47:03'),
(62, 'nsfile', 2791, '2016-02-28 17:44:04'),
(63, 'spamandnsfile', 0, '2016-02-28 12:18:07');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `athena_stats`
--
ALTER TABLE `athena_stats`
  ADD PRIMARY KEY (`as_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `athena_stats`
--
ALTER TABLE `athena_stats`
  MODIFY `as_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
