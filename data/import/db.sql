ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';
DROP USER IF EXISTS 'search'@'%';

CREATE USER 'search'@'%' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON *.* TO 'search'@'%';

CREATE DATABASE IF NOT EXISTS `hashcode` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
use hashcode;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(128) NOT NULL,
    email VARCHAR(256) DEFAULT NULL,
    password VARCHAR(512) DEFAULT NULL,
    token VARCHAR(256) DEFAULT NULL);


CREATE TABLE `hashcode`.`files` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `track_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(512) NOT NULL,
    `path` VARCHAR(512) NOT NULL,
     PRIMARY KEY (`id`));

ALTER TABLE `hashcode`.`files` ADD FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

DROP TABLE IF EXISTS `hashcode`.`path`;
CREATE TABLE `hashcode`.`path` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `geohash` varchar(7) NOT NULL,
  `track_id` INT UNSIGNED NOT NULL,
  `mapmatched` varchar(1) NOT NULL,
  `type` varchar(16) NOT NULL,
  `timestamp` TIMESTAMP NOT NULL,
  `length` FLOAT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `hashcode`.`path` ADD FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

DROP TABLE IF EXISTS `hashcode`.`tracks`;
CREATE TABLE `hashcode`.`tracks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `track_id` INT UNSIGNED NOT NULL,
  `filename` varchar(250) NOT NULL,
  `track` mediumtext NOT NULL,
  `mapmatched` varchar(80) NOT NULL,
  `type` varchar(80) NOT NULL,
  `timestamp` TIMESTAMP NOT NULL,
  `length` FLOAT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `hashcode`.`tracks` ADD FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- SET GLOBAL local_infile=1;
-- load data local infile '/home/data/import/files/path.csv' into table path fields terminated by ',' lines terminated by '\n' ignore 1 lines (hash, track);
-- load data local infile '/home/data/import/files/track.csv' into table tracks fields terminated by ';' lines terminated by '\n' ignore 1 lines (route, track);