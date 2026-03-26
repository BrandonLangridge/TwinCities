<?php
/**
 * setup.php
 *
 * Database Initialization Script
 * This script automates the creation of the database schema, defines table relationships,
 * and populates the system with initial seed data for Cities, POIs, and News.
 */

ob_start(); // Start output buffering to prevent header issues
require_once 'config.php';

// Extract database credentials from the global configuration array
$host   = $config['db']['host'];
$user   = $config['db']['user'];
$pass   = $config['db']['pass'];
$dbname = $config['db']['name'];

try {
    // 1. Establish initial connection to the MySQL server
    // Note: We connect without a database name first to allow for the DROP/CREATE logic below.
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Database Fresh Start
    // WARNING: This will delete any existing data in the specified database name.
    $pdo->exec("DROP DATABASE IF EXISTS `$dbname`");
    $pdo->exec("CREATE DATABASE `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // 3. Schema Definition
    // Tables are defined in an array and executed sequentially to maintain Foreign Key integrity.
    $tableQueries = [
        // Primary entity: All other tables depend on city_id
        "CREATE TABLE Cities (
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

        // User interaction table: Linked to Cities
        "CREATE TABLE Comments (
            comments_id INT AUTO_INCREMENT PRIMARY KEY,
            user_name VARCHAR(100) NOT NULL,
            comment_text TEXT NOT NULL,
            search_query VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            city_id INT NOT NULL,
            CONSTRAINT fk_comments_city FOREIGN KEY (city_id) REFERENCES Cities(city_id) ON DELETE CASCADE,
            INDEX (search_query)
        ) ENGINE=InnoDB",

        // Points of Interest: Detailed tourist/geographical data
        "CREATE TABLE Place_of_Interest (
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
            CONSTRAINT fk_poi_city FOREIGN KEY (city_id) REFERENCES Cities(city_id) ON DELETE CASCADE
        ) ENGINE=InnoDB",

        // Media assets: Includes optimized index for the search widget
        "CREATE TABLE Photos (
            photo_id INT AUTO_INCREMENT PRIMARY KEY,
            city_id INT NOT NULL,
            page_num INT NOT NULL,
            image_url VARCHAR(512) NOT NULL,
            caption VARCHAR(255) DEFAULT NULL,
            cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            CONSTRAINT fk_photos_city FOREIGN KEY (city_id) REFERENCES Cities(city_id) ON DELETE CASCADE,
            INDEX (city_id, caption)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // News feed: Localized headlines and content
        "CREATE TABLE News (
            news_id INT AUTO_INCREMENT PRIMARY KEY,
            headline VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
            city_id INT NOT NULL,
            CONSTRAINT fk_news_city FOREIGN KEY (city_id) REFERENCES Cities(city_id) ON DELETE CASCADE
        ) ENGINE=InnoDB"
    ];

    // Execute table creation loop
    foreach ($tableQueries as $query) {
        $pdo->exec($query);
    }

    // 4. Seed Data: Master City Records
    $citySql = "
    INSERT INTO Cities (city_id, name, country, population, latitude, longitude, currency, description) VALUES
        (1, 'Liverpool', 'UK', 496784, 53.4084, -2.9916, 'GBP', 'A maritime city in northwest England.'),
        (2, 'Cologne', 'Germany', 1086000, 50.9375, 6.9603, 'EUR', 'A 2,000-year-old city spanning the Rhine River.');
    ";
    $pdo->exec($citySql);

    // 5. Seed Data: Places of Interest (POIs)
    $poiSql = "
    INSERT INTO Place_of_Interest (name, type, capacity, latitude, longitude, description, year_opened, entry_fee, rating, city_id) VALUES
        ('The Beatles Story', 'Museum', NULL, 53.39930300, -2.99206600, 'Museum dedicated to the life and music of The Beatles.', 1990, '£18', 4.5, 1),
        ('Liverpool Cathedral', 'Religious Site', 2200, 53.39744600, -2.97317000, 'The largest cathedral in the UK.', 1924, 'Free', 4.8, 1),
        ('Royal Liver Building', 'Historic Building', NULL, 53.40587200, -2.99584800, 'Iconic early skyscraper on Liverpool waterfront.', 1911, 'Paid tours', 4.6, 1),
        ('Walker Art Gallery', 'Art Gallery', NULL, 53.41005900, -2.97963900, 'National gallery of arts for the North West.', 1877, 'Free', 4.6, 1),
        ('Anfield Stadium', 'Sports Venue', 61000, 53.43095100, -2.96090100, 'Historic football stadium and home of Liverpool FC.', 1884, '£23', 4.9, 1),
        ('Sefton Park', 'Park', NULL, 53.38256000, -2.93657000, 'Large Victorian public park covering 235 acres.', 1872, 'Free', 4.6, 1),
        ('Cologne Cathedral', 'Religious Site', 20000, 50.94133400, 6.95813300, 'Gothic Roman Catholic cathedral and UNESCO site.', 1880, 'Free', 4.8, 2),
        ('Museum Ludwig', 'Museum', NULL, 50.94084900, 6.96003700, 'Museum of modern and contemporary art.', 1976, '€12', 4.5, 2),
        ('Hohenzollern Bridge', 'Landmark', NULL, 50.94140700, 6.96585800, 'Iconic railway and pedestrian bridge.', 1911, 'Free', 4.7, 2),
        ('Cologne Zoo', 'Zoo', NULL, 50.96159000, 6.97655000, 'One of the oldest zoological gardens in Germany.', 1860, '€23', 4.6, 2),
        ('Roman-Germanic Museum', 'Museum', NULL, 50.94055400, 6.95866400, 'Archaeological museum with Roman artefacts.', 1974, '€9', 4.5, 2),
        ('Cologne City Hall', 'Historic Building', NULL, 50.93863400, 6.95873900, 'One of the oldest town halls in Germany.', 1135, 'Free', 4.4, 2);
    ";
    $pdo->exec($poiSql);

    // 6. Seed Data: News Articles
    $newsSql = "
    INSERT INTO News (headline, body, published_at, city_id) VALUES
        ('Liverpool Waterfront UNESCO', 'The iconic waterfront celebrated as a commercial architecture masterpiece.', '2025-10-15 09:00:00', 1),
        ('Anfield Expansion Approved', 'Council approves plans to increase capacity to over 61,000.', '2025-11-02 14:30:00', 1),
        ('Walker Gallery Exhibition', 'Major new exhibition showcasing contemporary artists opened.', '2025-12-01 10:00:00', 1),
        ('Cologne Cathedral Restoration', 'Iconic twin spires set to be fully unveiled in 2026.', '2025-09-20 08:00:00', 2),
        ('Love Locks Preserved', 'Authorities reverse proposal to remove locks from Hohenzollern Bridge.', '2025-10-30 11:15:00', 2),
        ('Museum Ludwig Acquisition', 'Museum acquires twelve previously unseen works by Picasso.', '2025-11-18 16:00:00', 2);
    ";
    $pdo->exec($newsSql);

} catch (Exception $e) {
    // Standard error handling: Stop execution and output the specific PDO error
    die("Setup Failed: " . $e->getMessage());
}

// Successful completion: Redirect to homepage
header("Location: index.php");
exit;