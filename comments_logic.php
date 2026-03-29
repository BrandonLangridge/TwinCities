<?php
/* comments_logic.php */

//Purpose: Handles CRUD (Create, Read, Delete) orders for city only comments


// LINK TO CONFIG,PHP
// This provides the $pdo connection and ensures consistent DB settings. 
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
    /* If the comments are somehow already stored inside of the session cache, it will return them back to nothing
    this should reduce the database load and speed up the repeated requests. */
    if (isset($_SESSION['comment_cache'][$cityId])) {
        return $_SESSION['comment_cache'][$cityId];
    }

    // SQL QUERY
    /* Using a organised statement to make the comments of queries a given city
     this is sorted by newest. */
    $sql = "SELECT * FROM Comment
            WHERE city_id = :cid 
            ORDER BY created_at DESC";
            
    $stmt = $pdo->prepare($sql);
    
    // Using Prepared Statements to prevent SQL Injection.
    $stmt->execute([
        'cid' => $cityId
    ]);
    // Fetch results as a asscoiative array
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cache results in the session for future possible requests
    $_SESSION['comment_cache'][$cityId] = $results;

    return $results;
}

/* POST LOGIC (Create)

Purpose: Processes new comment submissions from the form. */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    
    $cityId  = $_POST['city_id'] ?? null;
    
    /* Cleans the inputs to prevent the XSS (cross-site scripting).
    converts </script> into HTML entities and will present as harmless text. */
    $user    = htmlspecialchars($_POST['user_name'] ?? 'Anonymous');
    $comment = htmlspecialchars($_POST['comment_text'] ?? '');
    
    // This is a query paramater for contect.
    $query   = $_POST['search_param'] ?? '';

    // This function will only proceed if the text and the comment have been provided.
if ($cityId && !empty($comment)) {
    /* Server-side validation, checking character limit here in PHP ensures the DB
    doesn't get overloaded */
    if (strlen($comment) > 2000) {
        // Strip off any exsisting "anchors" from the URL before adding new ones.
        $url = preg_replace('/#.*$/', '', $_SERVER['HTTP_REFERER']);
        $sep = (strpos($url, '?') !== false) ? '&' : '?';
        /* #comments-section after the page reloads the browser automatically scrolls down
        to the comments area so the user doesn't have to search for their new post. */
        header("Location: " . $url . $sep . "announce=error_too_long#comments-section");
        exit;
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO Comment (user_name, comment_text, search_query, city_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user, $comment, $query, $cityId]);

            // CACHE INVALIDATION 

            // Remove cached comments for this city to make sure the new comment is shown on the next fetch.
            unset($_SESSION['comment_cache'][$cityId]);
            // Redirects to the page where the comment was submitted, with anchor to comments section.
            $redirectUrl = $_SERVER['HTTP_REFERER'];
            // Remove any existing anchor
            $redirectUrl = preg_replace('/#.*$/', '', $redirectUrl);
            // Add a page-load announce token so the page can speak after reload.
            if (strpos($redirectUrl, '?') !== false) {
                $redirectUrl .= '&announce=post_comment';
            } else {
                $redirectUrl .= '?announce=post_comment';
            }
            // Add comments section anchor.
            $redirectUrl .= '#comments-section';
            header("Location: " . $redirectUrl);
            exit;
        } catch (PDOException $e) {
            die("Error saving comment: " . $e->getMessage());
        }
    } else {
        die("Error: Missing city ID or comment text.");
    }
}

/* DELETE LOGIC
  Purpose: Permanently removes a specific comment from the database. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    
    $deleteId = $_POST['delete_id'] ?? null; // This is a comment ID made to delete.
    $cityId   = $_POST['city_id'] ?? null; // City ID made for cache invalidation. 

    if ($deleteId && $cityId) {
        try {
            //Delete comment safely using  the prepared statement.
            $stmt = $pdo->prepare("DELETE FROM Comment WHERE comment_id = ?");
            $stmt->execute([$deleteId]);

            /* CACHE INVALIDATION */

            // Removing the cached comments so that deletion is made to reflect.
            unset($_SESSION['comment_cache'][$cityId]);
            // Redirect back to the referring page, with anchor to comments section.
            $redirectUrl = $_SERVER['HTTP_REFERER'];
            // Remove any existing anchor.
            $redirectUrl = preg_replace('/#.*$/', '', $redirectUrl);
            // Add a page-load announce token so the page can speak after reload (comment removed).
            if (strpos($redirectUrl, '?') !== false) {
                $redirectUrl .= '&announce=comment_deleted';
            } else {
                $redirectUrl .= '?announce=comment_deleted';
            }
            // Add comments section anchor.
            $redirectUrl .= '#comments-section';
            header("Location: " . $redirectUrl);
            exit;
        } catch (PDOException $e) {
            die("Error deleting comment: " . $e->getMessage());
        }
    } else {
        die("Error: Missing comment ID for deletion.");
    }
}
?>
