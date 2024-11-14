ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';
CREATE USER 'search'@'%' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON *.* TO 'search'@'%';

CREATE DATABASE IF NOT EXISTS `hashcode` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
use hashcode;

CREATE TABLE IF NOT EXISTS pouzivatel (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meno VARCHAR(128) NOT NULL,
    email VARCHAR(256) NOT NULL,
    heslo VARCHAR(512) NOT NULL);


CREATE TABLE `hashcode`.`subor` (
    `id` INT UNSIGNED AUTO_INCREMENT,
    `pouzivatel_id` INT UNSIGNED NOT NULL,
    `nazov` VARCHAR(512) NOT NULL,
    `cesta` VARCHAR(512) NOT NULL,
     PRIMARY KEY (`id`));

ALTER TABLE `hashcode`.`subor` ADD FOREIGN KEY (`pouzivatel_id`) REFERENCES `pouzivatel`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

DROP TABLE IF EXISTS `path`;
CREATE TABLE `path` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hash` varchar(7) NOT NULL,
  `track` varchar(80) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ihash` (`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `tracks`;
CREATE TABLE `tracks` (
  `route` varchar(250) NOT NULL,
  `track` mediumtext NOT NULL,
  PRIMARY KEY (`route`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SET GLOBAL local_infile=1;
-- load data local infile '/home/data/import/files/path.csv' into table path fields terminated by ',' lines terminated by '\n' ignore 1 lines (hash, track);
-- load data local infile '/home/data/import/files/track.csv' into table tracks fields terminated by ';' lines terminated by '\n' ignore 1 lines (route, track);