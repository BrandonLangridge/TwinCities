<?php
/**
 * setup.php - Safe Database Initialization Script
 * This version is idempotent: it can run multiple times without wiping existing data.
 */

ob_start(); // Prevent header issues
require_once 'config.php';

// Database credentials
$host   = $config['db']['host'];
$user   = $config['db']['user'];
$pass   = $config['db']['pass'];
$dbname = $config['db']['name'];

try {
    // 1. Connect to MySQL server (no DB selected yet)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // 3. Create tables if not exists
    $tableQueries = [
        "CREATE TABLE IF NOT EXISTS City (
            city_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            country VARCHAR(100) NOT NULL,
            population INT NOT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            currency VARCHAR(4) NOT NULL,
            description TEXT NULL,
            UNIQUE (name, country)
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS Comment (
            comment_id INT AUTO_INCREMENT PRIMARY KEY,
            user_name VARCHAR(100) NOT NULL,
            comment_text TEXT NOT NULL,
            search_query VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            city_id INT NOT NULL,
            CONSTRAINT fk_comment_city FOREIGN KEY (city_id) REFERENCES City(city_id) ON DELETE CASCADE,
            INDEX (search_query)
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS Place_of_Interest (
            poi_id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            type VARCHAR(50) NOT NULL,
            capacity INT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            description TEXT NULL,
            year_opened INT NULL,
            entry_fee VARCHAR(32) NULL,
            rating DECIMAL(2,1) NULL,
            city_id INT NOT NULL,
            CONSTRAINT fk_poi_city FOREIGN KEY (city_id) REFERENCES City(city_id) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS Photo (
            photo_id INT AUTO_INCREMENT PRIMARY KEY,
            city_id INT NOT NULL,
            page_num INT NOT NULL,
            image_url VARCHAR(512) NOT NULL,
            caption VARCHAR(255) DEFAULT NULL,
            cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            CONSTRAINT fk_photo_city FOREIGN KEY (city_id) REFERENCES City(city_id) ON DELETE CASCADE,
            INDEX (city_id, caption)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS News (
            news_id INT AUTO_INCREMENT PRIMARY KEY,
            headline VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            city_id INT NOT NULL,
            CONSTRAINT fk_news_city FOREIGN KEY (city_id) REFERENCES City(city_id) ON DELETE CASCADE
        ) ENGINE=InnoDB"
    ];

    foreach ($tableQueries as $query) {
        $pdo->exec($query);
    }

    // 4. Seed Cities (only if missing)
    $cities = [
        ['Liverpool','UK',496784,53.4084,-2.9916,'GBP','A maritime city in northwest England.'],
        ['Cologne','Germany',1086000,50.9375,6.9603,'EUR','A 2,000-year-old city spanning the Rhine River.']
    ];

    $stmtCheckCity = $pdo->prepare("SELECT COUNT(*) FROM City WHERE name = ? AND country = ?");
    $stmtInsertCity = $pdo->prepare("INSERT INTO City (name, country, population, latitude, longitude, currency, description) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($cities as $c) {
        [$name, $country, $pop, $lat, $lon, $currency, $desc] = $c;
        $stmtCheckCity->execute([$name, $country]);
        if ($stmtCheckCity->fetchColumn() == 0) {
            $stmtInsertCity->execute([$name, $country, $pop, $lat, $lon, $currency, $desc]);
        }
    }

    // 5. Seed POIs (only if missing)
    $poiRows = [
        ['The Beatles Story','Museum',null,53.39930300,-2.99206600,'Museum dedicated to the life and music of The Beatles.',1990,'£18',4.5,'Liverpool'],
        ['Liverpool Cathedral','Religious Site',2200,53.39744600,-2.97317000,'The largest cathedral in the UK.',1924,'Free',4.8,'Liverpool'],
        ['Royal Liver Building','Historic Building',null,53.40587200,-2.99584800,'Iconic early skyscraper on Liverpool waterfront.',1911,'Paid tours',4.6,'Liverpool'],
        ['Walker Art Gallery','Art Gallery',null,53.41005900,-2.97963900,'National gallery of arts for the North West.',1877,'Free',4.6,'Liverpool'],
        ['Anfield Stadium','Sports Venue',61000,53.43095100,-2.96090100,'Historic football stadium and home of Liverpool FC.',1884,'£23',4.9,'Liverpool'],
        ['Sefton Park','Park',null,53.38256000,-2.93657000,'Large Victorian public park covering 235 acres.',1872,'Free',4.6,'Liverpool'],
        ['Cologne Cathedral','Religious Site',20000,50.94133400,6.95813300,'Gothic Roman Catholic cathedral and UNESCO site.',1880,'Free',4.8,'Cologne'],
        ['Museum Ludwig','Museum',null,50.94084900,6.96003700,'Museum of modern and contemporary art.',1976,'€12',4.5,'Cologne'],
        ['Hohenzollern Bridge','Landmark',null,50.94140700,6.96585800,'Iconic railway and pedestrian bridge.',1911,'Free',4.7,'Cologne'],
        ['Cologne Zoo','Zoo',null,50.96159000,6.97655000,'One of the oldest zoological gardens in Germany.',1860,'€23',4.6,'Cologne'],
        ['Roman-Germanic Museum','Museum',null,50.94055400,6.95866400,'Archaeological museum with Roman artefacts.',1974,'€9',4.5,'Cologne'],
        ['Cologne City Hall','Historic Building',null,50.93863400,6.95873900,'One of the oldest town halls in Germany.',1135,'Free',4.4,'Cologne']
    ];

    $stmtCheckPOI = $pdo->prepare("SELECT COUNT(*) FROM Place_of_Interest WHERE name = ? AND city_id = (SELECT city_id FROM City WHERE name = ?)");
    $stmtInsertPOI = $pdo->prepare("INSERT INTO Place_of_Interest (name,type,capacity,latitude,longitude,description,year_opened,entry_fee,rating,city_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, (SELECT city_id FROM City WHERE name = ?))");

    foreach ($poiRows as $p) {
        [$name,$type,$capacity,$lat,$lon,$desc,$year,$fee,$rating,$cityName] = $p;
        $stmtCheckPOI->execute([$name,$cityName]);
        if ($stmtCheckPOI->fetchColumn() == 0) {
            $stmtInsertPOI->execute([$name,$type,$capacity,$lat,$lon,$desc,$year,$fee,$rating,$cityName]);
        }
    }

    // 6. Seed News (only if missing)
    $newsRows = [
        ['Liverpool Waterfront UNESCO','The iconic waterfront celebrated as a commercial architecture masterpiece.','2025-10-15 09:00:00','Liverpool'],
        ['Anfield Expansion Approved','Council approves plans to increase capacity to over 61,000.','2025-11-02 14:30:00','Liverpool'],
        ['Walker Gallery Exhibition','Major new exhibition showcasing contemporary artists opened.','2025-12-01 10:00:00','Liverpool'],
        ['Cologne Cathedral Restoration','Iconic twin spires set to be fully unveiled in 2026.','2025-09-20 08:00:00','Cologne'],
        ['Love Locks Preserved','Authorities reverse proposal to remove locks from Hohenzollern Bridge.','2025-10-30 11:15:00','Cologne'],
        ['Museum Ludwig Acquisition','Museum acquires twelve previously unseen works by Picasso.','2025-11-18 16:00:00','Cologne']
    ];

    $stmtCheckNews = $pdo->prepare("SELECT COUNT(*) FROM News WHERE headline = ? AND city_id = (SELECT city_id FROM City WHERE name = ?)");
    $stmtInsertNews = $pdo->prepare("INSERT INTO News (headline,body,published_at,city_id) VALUES (?, ?, ?, (SELECT city_id FROM City WHERE name = ?))");

    foreach ($newsRows as $n) {
        [$headline,$body,$published_at,$cityName] = $n;
        $stmtCheckNews->execute([$headline,$cityName]);
        if ($stmtCheckNews->fetchColumn() == 0) {
            $stmtInsertNews->execute([$headline,$body,$published_at,$cityName]);
        }
    }

} catch (Exception $e) {
    // Let the global exception handler manage it
    throw $e;
}

// Redirect to homepage after setup
header("Location: index.php");
exit;