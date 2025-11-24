<?php
// config.php
// FIXED: Using LOCALHOST credentials and the new unified database name.
mysqli_report(MYSQLI_REPORT_OFF);

$host = 'localhost';
$user = 'root'; 
$pass = ''; 
$db   = 'u763865560_EmmanuelCafeDB'; // Set to the user's new main database name

// 1) Connect to MySQL server (without selecting a DB first)
$server = mysqli_connect($host, $user, $pass);
if (!$server) {
    die('MySQL connection failed: ' . mysqli_connect_error());
}

// 2) Ensure target database exists
if (!mysqli_select_db($server, $db)) {
    $createDbSql = "CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if (!mysqli_query($server, $createDbSql)) {
        die("Database '$db' does not exist and could not be created. Error: " . mysqli_error($server));
    }
}

// 3) Connect to the application database
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die('Database connect failed: ' . mysqli_connect_error());
}

// 4) Ensure required tables exist (minimal schema for `users` used by the app)
$createUsersSql = "CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `contact` VARCHAR(20) DEFAULT NULL,
  `gender` VARCHAR(20) DEFAULT NULL,
  `role` ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user',
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($conn, $createUsersSql);

// Modify existing role column to support super_admin if table exists
@$conn->query("ALTER TABLE `users` MODIFY `role` ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user'");

// Ensure profile_picture column exists for existing tables
$chkProfile = $conn->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='users' AND COLUMN_NAME='profile_picture'");
if ($chkProfile) {
    $chkProfile->bind_param('s', $db);
    $chkProfile->execute();
    $resProfile = $chkProfile->get_result();
    $rowProfile = $resProfile ? $resProfile->fetch_assoc() : null;
    $existsProfile = $rowProfile && (int)$rowProfile['c'] > 0;
    $chkProfile->close();
    if (!$existsProfile) {
        @$conn->query("ALTER TABLE `users` ADD COLUMN `profile_picture` VARCHAR(255) NULL AFTER `role`");
    }
}

// Ensure contact column exists
$chkContact = $conn->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='users' AND COLUMN_NAME='contact'");
if ($chkContact) {
    $chkContact->bind_param('s', $db);
    $chkContact->execute();
    $resContact = $chkContact->get_result();
    $rowContact = $resContact ? $resContact->fetch_assoc() : null;
    $existsContact = $rowContact && (int)$rowContact['c'] > 0;
    $chkContact->close();
    if (!$existsContact) {
        @$conn->query("ALTER TABLE `users` ADD COLUMN `contact` VARCHAR(20) NULL AFTER `email`");
    }
}

// Ensure gender column exists
$chkGender = $conn->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='users' AND COLUMN_NAME='gender'");
if ($chkGender) {
    $chkGender->bind_param('s', $db);
    $chkGender->execute();
    $resGender = $chkGender->get_result();
    $rowGender = $resGender ? $resGender->fetch_assoc() : null;
    $existsGender = $rowGender && (int)$rowGender['c'] > 0;
    $chkGender->close();
    if (!$existsGender) {
        @$conn->query("ALTER TABLE `users` ADD COLUMN `gender` VARCHAR(20) NULL AFTER `contact`");
    }
}

// 4b) Ensure recycle-bin table for users exists to avoid admin panel fatal errors
$createDeletedUsersSql = "CREATE TABLE IF NOT EXISTS `recently_deleted_users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `original_id` INT(11) NOT NULL,
  `fullname` VARCHAR(255) NOT NULL,
  `username` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'user',
  `deleted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
mysqli_query($conn, $createDeletedUsersSql);

// 4c) Ensure audit log table exists in the main database
$createAuditSql = "CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(64) NOT NULL,
  `entity_type` VARCHAR(64) NULL,
  `entity_id` INT NULL,
  `details` JSON NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`entity_type`),
  INDEX (`entity_id`),
  INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($conn, $createAuditSql);

// 4d) Ensure OTP codes table exists for email OTP login
$createOtpSql = "CREATE TABLE IF NOT EXISTS `otp_codes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `attempts` TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($conn, $createOtpSql);

// 4e) Ensure password_resets table exists for forgot password functionality
$createPasswordResetsSql = "CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `reset_code` VARCHAR(10) NOT NULL,
  `reset_method` ENUM('email','phone') NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `attempts` TINYINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`reset_code`),
  INDEX (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($conn, $createPasswordResetsSql);

// OTP feature toggles
if (!defined('OTP_ENABLED')) define('OTP_ENABLED', true);              // master switch
if (!defined('OTP_REQUIRE_FOR_ADMINS')) define('OTP_REQUIRE_FOR_ADMINS', true); // OTP now required for admins

// 5) Ensure tables (orders, cart, products) in this unified DB have necessary columns
$schema = $db;

// Check 'orders' table for user_id
$chk = $conn->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='orders' AND COLUMN_NAME='user_id'");
if ($chk) {
    $chk->bind_param('s', $db);
    $chk->execute();
    $res = $chk->get_result();
    $exists = $res && ($res->fetch_assoc()['c']??0)>0;
    $chk->close();
    if (!$exists) { 
        @$conn->query("ALTER TABLE `orders` ADD COLUMN `user_id` INT NULL AFTER `id`"); 
        @$conn->query("CREATE INDEX IF NOT EXISTS idx_orders_user_id ON `orders` (`user_id`)"); 
    } 
}

// Check 'cart' table for linkage and cancellation fields
$chk = $conn->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='cart' AND COLUMN_NAME='user_id'");
if ($chk) { 
    $chk->bind_param('s', $db); $chk->execute(); $res=$chk->get_result(); $exists = ($res && ($res->fetch_assoc()['c']??0)>0); $chk->close(); 
    if (!$exists) { 
        @$conn->query("ALTER TABLE `cart` ADD COLUMN `user_id` INT NULL AFTER `id`"); 
        @$conn->query("CREATE INDEX IF NOT EXISTS idx_cart_user_id ON `cart` (`user_id`)"); 
    } 
}
$chk = $conn->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='cart' AND COLUMN_NAME='cancel_reason'");
if ($chk) { 
    $chk->bind_param('s', $db); $chk->execute(); $res=$chk->get_result(); $exists = ($res && ($res->fetch_assoc()['c']??0)>0); $chk->close(); 
    if (!$exists) { 
        @$conn->query("ALTER TABLE `cart` ADD COLUMN `cancel_reason` VARCHAR(255) NULL AFTER `status`"); 
    } 
}
$chk = $conn->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='cart' AND COLUMN_NAME='cancelled_at'");
if ($chk) { 
    $chk->bind_param('s', $db); $chk->execute(); $res=$chk->get_result(); $exists = ($res && ($res->fetch_assoc()['c']??0)>0); $chk->close(); 
    if (!$exists) { 
        @$conn->query("ALTER TABLE `cart` ADD COLUMN `cancelled_at` TIMESTAMP NULL AFTER `cancel_reason`"); 
    } 
}
?>
