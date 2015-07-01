<?php
/**
 * @author Gerrit Addiks <gerrit.addiks@brille24.de>
 */

use Addiks\PHPSQL\Resource\Connection;
use Addiks\PHPSQL\Value\Database\Dsn;

define("BASEDIR", dirname(__FILE__));

function __autoload($className)
{
    $filePath = "src/".str_replace("\\", "//", $className).".php";
    require_once($className);
}

// create a new in-memory database
$connection = Connection::newFromDsn(Dsn::factory("inmemory"));

// create a few tables inside that database
// (the ENGINE definition will be ignored)
$connection->query("

    CREATE TABLE `product` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(64) DEFAULT NULL,
      `price` float DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;

    CREATE TABLE `customer` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(64) DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;

    CREATE TABLE `cart_item` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `customer_id` int(11) DEFAULT NULL,
      `product_id` int(11) DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `fk_cusotmer_idx` (`customer_id`),
      KEY `fk_product_idx` (`product_id`),
      CONSTRAINT `fk_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
      CONSTRAINT `fk_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
    ) ENGINE=InnoDB DEFAULT CHARSET=latin1;

");

// Oops, forgot the quantity on that cart-item table, let's add it
$connection->query("ALTER TABLE `cart_item` ADD COLUMN `quantity` FLOAT NOT NULL DEFAULT 1.0");

// Let's have a look on the table 'cart_item'
echo (string)$connection->query("DESCRIBE TABLE `cart_item`");


