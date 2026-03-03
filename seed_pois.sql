-- ============================================================
-- seed_pois.sql  (fixed version)
-- Uses DELETE instead of TRUNCATE to avoid FK constraint errors.
-- ============================================================

USE city_twin_db;

-- Delete child records first (Images references Place_of_Interest)
DELETE FROM Images;

-- Now safe to delete all POIs
DELETE FROM Place_of_Interest;

-- Reset auto-increment counter to start from 1
ALTER TABLE Place_of_Interest AUTO_INCREMENT = 1;

-- ---------------------------------------------------------------
-- LIVERPOOL POIs  (city_id = 1)
-- ---------------------------------------------------------------

INSERT INTO Place_of_Interest (name, type, capacity, latitude, longitude, description, city_id) VALUES
('The Beatles Story', 'Museum', NULL, 53.39930300, -2.99206600, 'Museum dedicated to the life and music of The Beatles, located on Liverpool''s historic Albert Dock waterfront.', 1),
('Liverpool Cathedral', 'Religious Site', 2200, 53.39744600, -2.97317000, 'The largest cathedral in the UK and fifth largest in the world, an architectural landmark of the Anglican tradition completed in 1978.', 1),
('Royal Liver Building', 'Historic Building', NULL, 53.40587200, -2.99584800, 'Iconic early skyscraper on Liverpool''s waterfront, built in 1911 and famous for the mythical Liver Birds perched on its twin clock towers.', 1),
('Walker Art Gallery', 'Art Gallery', NULL, 53.41005900, -2.97963900, 'National gallery of arts for the North West of England, featuring an outstanding collection of paintings, sculpture and decorative art from 1300 to the present day.', 1),
('Anfield Stadium', 'Sports Venue', 61000, 53.43095100, -2.96090100, 'Historic football stadium and home of Liverpool FC. One of the most famous grounds in world football, opened in 1884 and expanded multiple times since.', 1),
('Sefton Park', 'Park', NULL, 53.38256000, -2.93657000, 'Large Victorian public park covering 235 acres with lakes, gardens, and walking paths. A Grade I listed landscape and much-loved green space in south Liverpool.', 1);

-- ---------------------------------------------------------------
-- COLOGNE POIs  (city_id = 2)
-- ---------------------------------------------------------------

INSERT INTO Place_of_Interest (name, type, capacity, latitude, longitude, description, city_id) VALUES
('Cologne Cathedral', 'Religious Site', 20000, 50.94133400, 6.95813300, 'Gothic Roman Catholic cathedral and UNESCO World Heritage Site, begun in 1248 and completed in 1880. The tallest twin-spired church in the world at 157 metres.', 2),
('Museum Ludwig', 'Museum', NULL, 50.94084900, 6.96003700, 'Museum of modern and contemporary art housing one of the most important Picasso collections in the world, alongside works by Warhol, Lichtenstein and other pop art masters.', 2),
('Hohenzollern Bridge', 'Landmark', NULL, 50.94140700, 6.96585800, 'Iconic railway and pedestrian bridge spanning the Rhine, completed in 1911. Famous for the thousands of love locks attached to its railings by couples from around the world.', 2),
('Cologne Zoo', 'Zoo', NULL, 50.96159000, 6.97655000, 'One of the oldest and most visited zoological gardens in Germany, founded in 1860. Home to over 10,000 animals representing around 700 species.', 2),
('Roman-Germanic Museum', 'Museum', NULL, 50.94055400, 6.95866400, 'Archaeological museum housing one of the most significant collections of Roman artefacts in Germany, including the magnificent 3rd-century Dionysus mosaic discovered in situ.', 2),
('Cologne City Hall', 'Historic Building', NULL, 50.93863400, 6.95873900, 'Historic city hall and one of the oldest town halls in Germany, with origins dating back to 1135. The Renaissance loggia added in 1569 is a particularly fine example of its period.', 2);