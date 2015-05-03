<?php

$screenOut = true;
require_once($_SERVER['DOCUMENT_ROOT'] . '/projects/php/addressbook/models/dbConnectClass.php');
$db = DBConnect::instantiateDB('', '', '', '', false, false);
require_once($_SERVER['DOCUMENT_ROOT'] . '/projects/php/addressbook/models/ContactClass.php');

/*
 * Cannot have more than 9400 contacts my lowsy shared hosting does not offer enough memory to support more
  generate_customers(10000);
 * 
 */
generate_customers(1000);

function generate_customers($amount = 10) {
    global $db;
//    $db->destroy("TRUNCATE TABLE `contact_phone_number`");
//    $db->destroy("TRUNCATE TABLE `contact_address`");
//    $db->destroy("TRUNCATE TABLE `contact`");
//    $db->destroy("TRUNCATE TABLE `tracking`");
    $db->destroy("DROP TABLE `contact_phone_number`");
    $db->destroy("DROP TABLE `contact_address`");
    $db->destroy("DROP TABLE `contact`");
    $db->destroy("DROP TABLE `tracking`");
    $db->build("CREATE TABLE IF NOT EXISTS `joshuaaddressbook`.`contact` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `first_name` VARCHAR(40) NOT NULL DEFAULT '',
    `middle_name` VARCHAR(40) NOT NULL DEFAULT '',
    `last_name` VARCHAR(40) NOT NULL DEFAULT '',
    `notes` BLOB,
    `email` VARCHAR(60) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    INDEX `full_name` (`first_name`,`middle_name`,`last_name`),
    FULLTEXT `name` (`first_name`,`middle_name`,`last_name`)    
) ENGINE=InnoDB");
    $db->build("CREATE TABLE IF NOT EXISTS `joshuaaddressbook`.`contact_address` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `contact_id` INT NOT NULL,
    `street` VARCHAR(120) NOT NULL DEFAULT '',
    `city` VARCHAR(40) NOT NULL DEFAULT '',
    `province` VARCHAR(6) NOT NULL DEFAULT '',
    `country` VARCHAR(40) NOT NULL DEFAULT '',
    `postal_code` VARCHAR(10) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    FOREIGN KEY (`contact_id`) REFERENCES `contact`(`id`)
) ENGINE=InnoDB");
    $db->build("CREATE TABLE IF NOT EXISTS `joshuaaddressbook`.`contact_phone_number` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `contact_id` INT NOT NULL,
    `phone_type` VARCHAR(8) NOT NULL DEFAULT '',
    `phone_number` VARCHAR(20) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    FOREIGN KEY (`contact_id`) REFERENCES `contact`(`id`)
) ENGINE=InnoDB");
    $db->build("CREATE TABLE IF NOT EXISTS `joshuaaddressbook`.`tracking` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `date_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `app_user` VARCHAR(60) NOT NULL DEFAULT '',
    `action` VARCHAR(120) NOT NULL DEFAULT '',
    `acting_class` VARCHAR(60) NOT NULL DEFAULT '',
    `class_id` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB");
    for ($i = 0; $i < $amount; ++$i) {
        $address = array();
        $address[] = new ContactAddress($db, -1, -1, generate_street($i), generate_city(), generate_province($i), generate_country($i), generate_postal($i));
        $phones = array();
        if ($i % 3 === 0) {
            $phones[] = new ContactPhoneNumber($db, -1, -1, 'home', generate_phone());
        }
        $phones[] = new ContactPhoneNumber($db, -1, -1, 'work', generate_phone());
        if ($i % 2 === 0) {
            $phones[] = new ContactPhoneNumber($db, -1, -1, 'cell', generate_phone());
        }
        $first_name = generate_name();
        $middle_name = $i % 2 === 0 ? generate_name() : "";
        $last_name = generate_name();
        $customer = new Contact($db, -1, $first_name, $middle_name, $last_name, $address, $phones, generate_email($first_name, $last_name), "");
        $customer->create_contact();
    }
    echo "Completed Creating {$amount} Contacts<br>";
}

function generate_street($seed) {
    $street = "";
    if ($seed % 3 === 0) {
        $street .= mt_rand(1, 2412) . "-" . mt_rand(1, 9999) . " " . generate_name(4, 20) . " Street";
    } else {
        $street .= mt_rand(1, 9999) . " " . generate_name(4, 20) . " Street";
    }
    return $street;
}

function generate_city() {
    return generate_name(4, 20);
}

function generate_province($seed) {
    $province = "";
    if ($seed % 2 === 0) {
        $locations = array("AB", "BC", "MB", "NB", "NL", "NS", "ON", "PE", "QC", "SK", "NT", "NU", "YT");
        $province = $locations[mt_rand(0, 12)];
    } else {
        $locations = array("AL", "AK", "AZ", "AR", "CA", "CO", "CT", "DE", "DC", "FL", "GA", "HI", "ID", "IL", "IN", "IA", "KS", "KY", "LA", "ME", "MD", "MA", "MI", "MN", "MS", "MO", "MT", "NE", "NV", "NH", "NJ", "NM", "NY", "NC", "ND", "OH", "OK", "OR", "PA", "RI", "SC", "SD", "TN", "TX", "UT", "VT", "VA", "WA", "WV", "WI", "WY");
        $province = $locations[mt_rand(0, 50)];
    }
    return $province;
}

function generate_country($seed) {
    return $seed % 2 === 0 ? "Canada" : "United States";
}

function generate_postal($seed) {
    $postal = "";
    if ($seed % 2 === 0) {
        $alpha = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $postal = $alpha[mt_rand(0, 25)] . mt_rand(0, 9) . $alpha[mt_rand(0, 25)] . " " . mt_rand(0, 9) . $alpha[mt_rand(0, 25)] . mt_rand(0, 9);
    } else {
        $postal = mt_rand(10000, 99999);
    }
    return $postal;
}

function generate_phone() {
    return mt_rand(1000000000, 9999999999);
}

function generate_name($min = 4, $max = 12) {
    $alpha_caps = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $alpha_lower = "abcdefghijklmnopqrstuvwxyz";
    $count = mt_rand(intval($min), intval($max));
    $name = "";
    for ($i = 0; $i < $count; ++$i) {
        if ($i === 0) {
            $name .= $alpha_caps[mt_rand(0, 25)];
        } else {
            $name .= $alpha_lower[mt_rand(0, 25)];
        }
    }
    return $name;
}

function generate_email($first, $last) {
    return $first[0] . "." . $last . "@domain.com";
}
