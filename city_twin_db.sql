-- Removes the database if its exists
-- able to prevent errors when it re-runs the script during dev
DROP DATABASE IF EXISTS city_twin_db;

-- Creates the main database used by the app 
-- UTF8mb4 allows full unicode support
CREATE DATABASE city_twin_db
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- Select the database so all following tables are created inside it 
USE city_twin_db;


-- Table: Cities
-- Stores core information about each city inn the system 
CREATE TABLE Cities (
  -- Unique indentifer for each city 
    city_id INT AUTO_INCREMENT PRIMARY KEY,
  
  -- Name of the city
    name VARCHAR(100) NOT NULL,
  
  -- name of country 
    country VARCHAR(100) NOT NULL,
  
  -- population size
    population INT NOT NULL,
  
  -- geographic latitude coodinate 
  -- decimal used for precise mapping
    latitude DECIMAL(10,8) NOT NULL,
  
  -- longitude mapping coodinate 
    longitude DECIMAL(11,8) NOT NULL,
  
  -- type of currency used in country
    currency VARCHAR(4) NOT NULL,
  
  -- description of the city
    description TEXT NULL,
  
  -- doesnt allow for double entries
    UNIQUE (name, country)
) ENGINE=InnoDB;


-- Table: Comments
CREATE TABLE Comments (
    -- Unique indentifier for each comment 
    comments_id INT AUTO_INCREMENT PRIMARY KEY,
  
    -- Name entered byt he user submitting the comment 
    user_name VARCHAR(100) NOT NULL,
  
    -- the comment text being displayed
    comment_text TEXT NOT NULL,
  
    -- Stores the search term the user used when posting the comment
    -- Useful for analytics 
    search_query VARCHAR(255) DEFAULT NULL,
  
    -- Timestamp automatically generated when the comment is created 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL,
  
    -- foreign key linking the comment to a specific city 
    city_id INT NOT NULL,
    -- Fpreign key constraint ensuring the referenced city exists
    -- if the city is deleted, all releted comments are also removed 
  
    CONSTRAINT fk_comments_city
        FOREIGN KEY (city_id)
        REFERENCES Cities(city_id)
        ON DELETE CASCADE,
        -- Index added to speed up the searches/ filtering by search query 
    INDEX (search_query)
) ENGINE=InnoDB;


-- Table: Place_of_Interest
CREATE TABLE Place_of_Interest (
    -- unique identifer for for each place of interest 
    poi_id INT AUTO_INCREMENT PRIMARY KEY,
  
    -- name of location 
    name VARCHAR(150) NOT NULL,
  
    -- type of location
    type VARCHAR(50) NOT NULL,
  
    -- maxumum vistor capacity 
    capacity INT NULL,
  
    -- geographic latitude coodinates
    -- decimal used for precise mapping   
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
  
    -- description of the location
    description TEXT NULL,
  
    city_id INT NOT NULL,
    -- Ensures the POI belongs to a valid city
    -- If the city is deleted, associated POIs are also deleted
    CONSTRAINT fk_poi_city
        FOREIGN KEY (city_id)
        REFERENCES Cities(city_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- Table: Images
CREATE TABLE Images (
    -- unique identifier for each image
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    -- URL location of stored image 
  
    -- Large length  allows use of clouds/CDN links
    image_url VARCHAR(2048) NOT NULL,
  
    -- optional caption describing the image 
    caption VARCHAR(255) NULL,
  
    -- foreign kkey linking the image to a specific place of interest 
    poi_id INT NOT NULL,
    -- makes sure the image belongs to a vaild POI
    -- If the POI is deleted, the image will also be deleted 
    CONSTRAINT fk_images_poi
        FOREIGN KEY (poi_id)
        REFERENCES Place_of_Interest(poi_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;
