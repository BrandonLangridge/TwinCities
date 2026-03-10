<?php
/* comments_logic.php */
//---------------------------
//purpose: Handles CRUD (Create, Read, Delete) orders for city only comments
// - Makes use of PDO for database interaction
// - uses session based chaching to lessen queries 
// - Secures the input against SQL injection and XSS exploits 

// Link to config.php
// This provides the $pdo connection and ensures consistent DB settings.
// provides DB connection accross all scripts  
require_once 'config.php'; 

// SESSION & CACHING INITIALISATION
// Check if a session is active. If not, we start one to store cached database results.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* FETCH LOGIC (Read)
   Purpose: Retrieves comments for a specific city while implementing caching. */
function getCommentsForCity($cityId, $pdo) {
    // CACHE CHECK 
    //if the comments are somehow already stored inside of the session cache, it will return them back to nothing
    //this should reduce the database to load and speed the repeated requests
    if (isset($_SESSION['comment_cache'][$cityId])) {
        return $_SESSION['comment_cache'][$cityId];
    }

    // SQL QUERY
    // Using a organised statement to make the comments of queries a given city
    // this is sorted by newest
    $sql = "SELECT * FROM Comments 
            WHERE city_id = :cid 
            ORDER BY created_at DESC";
            
    $stmt = $pdo->prepare($sql);
    
    // Using Prepared Statements to prevent SQL Injection.
    $stmt->execute([
        'cid' => $cityId
    ]);
    // Fetch results as a asscoiative array
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // cache results in the session for future possible requests
    $_SESSION['comment_cache'][$cityId] = $results;

    return $results;
}

/* POST LOGIC (Create)

// purpose: Processes new comment submissions from the form. */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    
    $cityId  = $_POST['city_id'] ?? null;
    
    // cleans the inputs to prevent the XSS (cross-site scripting)
    $user    = htmlspecialchars($_POST['user_name'] ?? 'Anonymous');
    $comment = htmlspecialchars($_POST['comment_text'] ?? '');
    
    // this is a query paramater for contect
    $query   = $_POST['search_param'] ?? '';

    // this function will only proceed if the text and the comment have been provided
    if ($cityId && !empty($comment)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO Comments (user_name, comment_text, search_query, city_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user, $comment, $query, $cityId]);

            // CACHE INVALIDATION 

            //remove cached comments for this city to make sure the new comment is shown on the next fetch
            unset($_SESSION['comment_cache'][$cityId]);
            // redirets to the page where the comment was submitted
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        } catch (PDOException $e) {
            die("Error saving comment: " . $e->getMessage());
        }
    } else {
        die("Error: Missing city ID or comment text.");
    }
}

/* DELETE LOGIC
  purpose: Permanently removes a specific comment from the database. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    
    $deleteId = $_POST['delete_id'] ?? null; // this is a comment ID made to delete
    $cityId   = $_POST['city_id'] ?? null; // City ID made for cache invalidation 

    if ($deleteId && $cityId) {
        try {
            //Delete comment safely using  the prepared statement
            $stmt = $pdo->prepare("DELETE FROM Comments WHERE comments_id = ?");
            $stmt->execute([$deleteId]);

            // CACHE INVALIDATION 

            // removing the cached comments so that deletion is made to reflect
            unset($_SESSION['comment_cache'][$cityId]);
            // redirect back to the referring page
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        } catch (PDOException $e) {
            die("Error deleting comment: " . $e->getMessage());
        }
    } else {
        die("Error: Missing comment ID for deletion.");
    }
}
?>
