-- Sally Database Dump Version 0.6
-- Prefix sly_

CREATE TABLE sly_article (id INT UNSIGNED NOT NULL, clang INT UNSIGNED NOT NULL, re_id INT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, catname VARCHAR(255) NOT NULL, catprior INT UNSIGNED NOT NULL, attributes TEXT NOT NULL, startpage TINYINT(1) NOT NULL, prior INT UNSIGNED NOT NULL, path VARCHAR(255) NOT NULL, status INT UNSIGNED NOT NULL, type VARCHAR(64) NOT NULL, createdate INT UNSIGNED NOT NULL, updatedate INT UNSIGNED NOT NULL, createuser VARCHAR(255) NOT NULL, updateuser VARCHAR(255) NOT NULL, revision INT UNSIGNED DEFAULT 0 NOT NULL, PRIMARY KEY(id, clang)) DEFAULT CHARACTER SET utf8 ENGINE = MyISAM;
CREATE TABLE sly_article_slice (id INT UNSIGNED NOT NULL, clang INT UNSIGNED NOT NULL, slot VARCHAR(64) NOT NULL, prior INT UNSIGNED NOT NULL, slice_id INT UNSIGNED DEFAULT 0 NOT NULL, article_id INT UNSIGNED NOT NULL, createdate INT UNSIGNED NOT NULL, updatedate INT UNSIGNED NOT NULL, createuser VARCHAR(255) NOT NULL, updateuser VARCHAR(255) NOT NULL, revision INT UNSIGNED DEFAULT 0 NOT NULL, INDEX find_article (article_id, clang), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 ENGINE = MyISAM;
CREATE TABLE sly_clang (id INT UNSIGNED AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, locale VARCHAR(5) NOT NULL, revision INT UNSIGNED DEFAULT 0 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 ENGINE = MyISAM;
CREATE TABLE sly_file (id INT UNSIGNED AUTO_INCREMENT NOT NULL, re_file_id INT UNSIGNED NOT NULL, category_id INT UNSIGNED NOT NULL, attributes TEXT NULL, filetype VARCHAR(255) NOT NULL, filename VARCHAR(255) NOT NULL, originalname VARCHAR(255) NOT NULL, filesize VARCHAR(255) NOT NULL, width INT UNSIGNED NOT NULL, height INT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL, createdate INT UNSIGNED NOT NULL, updatedate INT UNSIGNED NOT NULL, createuser VARCHAR(255) NOT NULL, updateuser VARCHAR(255) NOT NULL, revision INT UNSIGNED DEFAULT 0 NOT NULL, INDEX filename (filename), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 ENGINE = MyISAM;
CREATE TABLE sly_file_category (id INT UNSIGNED AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, re_id INT UNSIGNED NOT NULL, path VARCHAR(255) NOT NULL, attributes TEXT NULL, createdate INT UNSIGNED NOT NULL, updatedate INT UNSIGNED NOT NULL, createuser VARCHAR(255) NOT NULL, updateuser VARCHAR(255) NOT NULL, revision INT UNSIGNED DEFAULT 0 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 ENGINE = MyISAM;
CREATE TABLE sly_user (id INT UNSIGNED AUTO_INCREMENT NOT NULL, name VARCHAR(255) NULL, description VARCHAR(255) NULL, login VARCHAR(50) NOT NULL, psw CHAR(40), status TINYINT(1) NOT NULL, rights TEXT NOT NULL, lasttrydate INT UNSIGNED DEFAULT 0 NOT NULL, timezone VARCHAR(64) NULL, createdate INT UNSIGNED NOT NULL, updatedate INT UNSIGNED NOT NULL, createuser VARCHAR(255) NOT NULL, updateuser VARCHAR(255) NOT NULL, revision INT UNSIGNED DEFAULT 0 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 ENGINE = MyISAM;
CREATE TABLE sly_slice (id INT UNSIGNED AUTO_INCREMENT NOT NULL, module VARCHAR(64) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 ENGINE = MyISAM;
CREATE TABLE sly_slice_value (id INT UNSIGNED AUTO_INCREMENT NOT NULL, slice_id INT UNSIGNED NOT NULL, type VARCHAR(50) NOT NULL, finder VARCHAR(50) NOT NULL, value TEXT NOT NULL, INDEX slice_id (slice_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 ENGINE = MyISAM;
CREATE TABLE sly_registry (name VARCHAR(255) NOT NULL, value BLOB NOT NULL, PRIMARY KEY(name)) DEFAULT CHARACTER SET utf8 ENGINE = MyISAM;

-- populate database with some initial data
INSERT INTO sly_clang (name, locale) VALUES ('deutsch', 'de_DE');