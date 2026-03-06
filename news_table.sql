
USE city_twin_db;

-- SAMPLE DATA 

-- Liverpool news
INSERT INTO News (headline, body, published_at, city_id) VALUES
(
    'Liverpool Waterfront Awarded UNESCO Heritage Status',
    'The iconic Liverpool Waterfront, home to the Royal Liver Building and the Three Graces, '
    'has been celebrated as one of Europe''s finest examples of early-20th century commercial '
    'architecture. The site continues to attract millions of visitors each year and remains '
    'a cornerstone of the city''s tourism economy.',
    '2025-10-15 09:00:00',
    1
),
(
    'Anfield Stadium Expansion Plans Approved',
    'Liverpool City Council has approved plans for a further expansion of Anfield Stadium, '
    'which will increase its capacity to over 61,000 seats. The development is expected to '
    'boost matchday revenue and strengthen Liverpool FC''s position as one of Europe''s '
    'leading football clubs.',
    '2025-11-02 14:30:00',
    1
),
(
    'Walker Art Gallery Launches New Contemporary Exhibition',
    'The Walker Art Gallery in Liverpool has opened a major new exhibition showcasing '
    'contemporary artists from the North West of England. The free exhibition runs until '
    'March 2026 and features over 80 works spanning painting, sculpture, and digital media.',
    '2025-12-01 10:00:00',
    1
);

-- Cologne news 
INSERT INTO News (headline, body, published_at, city_id) VALUES
(
    'Cologne Cathedral Restoration Project Nears Completion',
    'After several years of restoration work, the iconic twin spires of Cologne Cathedral '
    'are set to be fully unveiled in 2026. The Gothic masterpiece, a UNESCO World Heritage '
    'Site, has undergone extensive cleaning and stonework repairs, restoring it to its '
    'original splendour.',
    '2025-09-20 08:00:00',
    2
),
(
    'Hohenzollern Bridge Love Locks to Be Preserved',
    'Cologne City authorities have reversed an earlier proposal to remove the thousands of '
    'love locks attached to the Hohenzollern Bridge. The locks, now weighing several tonnes, '
    'have become a beloved tourist attraction and a symbol of the city''s romantic character.',
    '2025-10-30 11:15:00',
    2
),
(
    'Museum Ludwig Acquires Major Picasso Collection',
    'The Museum Ludwig in Cologne has announced the acquisition of twelve previously '
    'unseen works by Pablo Picasso, donated by a private European collector. The works '
    'will go on permanent display from January 2026, significantly expanding the museum''s '
    'already world-class modern art holdings.',
    '2025-11-18 16:00:00',
    2
);

