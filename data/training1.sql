-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Mar 08, 2016 at 11:50 AM
-- Server version: 10.1.9-MariaDB
-- PHP Version: 7.0.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `training`
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
(1, 'pages', 2782, '2016-02-23 20:13:42'),
(2, 'spam', 313, '2016-02-24 22:36:57'),
(3, 'notspam', 2469, '2016-02-24 22:37:14'),
(4, 'difflang', 983, '1970-01-01 00:00:01'),
(5, 'samelang', 1799, '1970-01-01 00:00:01'),
(6, 'deleted', 58, '2016-02-23 19:36:12'),
(7, 'notdeleted', 2724, '2016-02-23 20:13:42'),
(8, 'wanted', 320, '2016-02-23 20:13:23'),
(9, 'notwanted', 2462, '2016-02-23 20:13:42'),
(10, 'userother', 2122, '1970-01-01 00:00:01'),
(11, 'anon', 453, '1970-01-01 00:00:01'),
(12, 'user1', 104, '1970-01-01 00:00:01'),
(13, 'user5', 5, '1970-01-01 00:00:01'),
(14, 'user30', 11, '1970-01-01 00:00:01'),
(15, 'user60', 11, '1970-01-01 00:00:01'),
(16, 'user12', 70, '1970-01-01 00:00:01'),
(17, 'user24', 6, '1970-01-01 00:00:01'),
(18, 'titlelength', 131, '2016-02-23 20:10:31'),
(19, 'nottitlelength', 2651, '2016-02-23 20:13:42'),
(20, 'nsmain', 1060, '2016-02-23 20:13:42'),
(21, 'nstalk', 226, '2016-02-23 19:36:12'),
(22, 'nsuser', 158, '2016-02-23 20:13:11'),
(23, 'nsusertalk', 41, '2016-02-23 19:16:40'),
(24, 'nsother', 439, '2016-02-23 19:01:53'),
(25, 'syntaxnone', 327, '1970-01-01 00:00:01'),
(26, 'syntaxbasic', 1116, '1970-01-01 00:00:01'),
(27, 'syntaxcomplex', 1339, '1970-01-01 00:00:01'),
(28, 'brokenspambot', 0, '1970-01-01 00:00:01'),
(29, 'links0', 2542, '2016-02-23 20:13:42'),
(30, 'links5', 17, '2016-02-23 20:10:31'),
(31, 'links20', 43, '2016-02-23 20:13:31'),
(32, 'links50', 180, '2016-02-23 20:13:11'),
(33, 'spamanddifflang', 57, '2016-02-24 22:36:49'),
(34, 'spamandsamelang', 256, '2016-02-24 22:36:57'),
(35, 'spamanddeleted', 44, '2016-02-24 22:23:19'),
(36, 'spamandnotdeleted', 269, '2016-02-24 22:36:57'),
(37, 'spamandwanted', 0, '2016-02-23 11:12:10'),
(38, 'spamandnotwanted', 313, '2016-02-24 22:36:57'),
(39, 'spamanduserother', 7, '1970-01-01 00:00:01'),
(40, 'spamandanon', 167, '1970-01-01 00:00:01'),
(41, 'spamanduser1', 104, '1970-01-01 00:00:01'),
(42, 'spamanduser5', 4, '1970-01-01 00:00:01'),
(43, 'spamanduser30', 6, '1970-01-01 00:00:01'),
(44, 'spamanduser60', 2, '1970-01-01 00:00:01'),
(45, 'spamanduser12', 16, '1970-01-01 00:00:01'),
(46, 'spamanduser24', 6, '1970-01-01 00:00:01'),
(47, 'spamandtitlelength', 45, '2016-02-24 22:36:04'),
(48, 'spamandnottitlelength', 268, '2016-02-24 22:36:57'),
(49, 'spamandnsmain', 106, '2016-02-24 22:36:53'),
(50, 'spamandnstalk', 45, '2016-02-24 22:23:19'),
(51, 'spamandnsuser', 138, '2016-02-24 22:36:57'),
(52, 'spamandnsusertalk', 11, '2016-02-24 21:42:10'),
(53, 'spamandnsother', 13, '2016-02-24 21:23:43'),
(54, 'spamandsyntaxnone', 174, '1970-01-01 00:00:01'),
(55, 'spamandsyntaxbasic', 138, '1970-01-01 00:00:01'),
(56, 'spamandsyntaxcomplex', 0, '1970-01-01 00:00:01'),
(57, 'spamandbrokenspambot', 0, '1970-01-01 00:00:01'),
(58, 'spamandlinks0', 299, '2016-02-24 22:36:57'),
(59, 'spamandlinks5', 11, '2016-02-24 22:36:04'),
(60, 'spamandlinks20', 2, '2016-02-24 22:23:45'),
(61, 'spamandlinks50', 1, '2016-02-24 21:21:42'),
(62, 'nsfile', 858, '2016-02-23 19:28:26'),
(63, 'spamandnsfile', 0, '2016-02-23 10:17:14');

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
