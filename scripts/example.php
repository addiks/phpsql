<?php
/**
 * @author Gerrit Addiks <gerrit.addiks@brille24.de>
 */

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Addiks\PHPSQL\PDO;
use Addiks\PHPSQL\Entity\Exception\MalformedSql;
use Addiks\PHPSQL\ResultWriter;

define("BASEDIR", realpath(__DIR__."/.."));

function __autoload($className)
{
    $filePath = str_replace("\\", "/", $className);
    $filePath = BASEDIR."/src/{$filePath}.php";
    if (file_exists($filePath)) {
        require_once($filePath);
    }
}

$pdo = new PDO("inmemory:some_example_database");

try{

    // create a few tables inside that database
    // (the ENGINE definition will be ignored)
    $pdo->query("

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
    $pdo->query("ALTER TABLE `cart_item` ADD COLUMN `quantity` FLOAT NOT NULL DEFAULT 1.0");

    // Let's have a look on the table 'cart_item'
    echo (string)new ResultWriter($pdo->query("DESCRIBE `cart_item`")->getResult());

} catch (MalformedSql $exception) {
    echo $exception;
}
