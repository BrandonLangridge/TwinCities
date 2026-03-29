<?php
$config = include __DIR__ . '/config.php';
?>


<!-- 404 page that is seen by a user who tries to view a non-existent page -->

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>A 404 - Surely You Can't Be Serious?</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-start justify-center h-screen font-sans pt-20">

    <div class="bg-white p-6 rounded-lg shadow-lg text-center max-w-2xl">
        <h1 class="text-2xl font-bold mb-4">A 404 - Page Not Found! Surely you can't be serious?</h1>
        <p class="mb-2 font-bold">I am serious. And don't call me Shirley.</p>
        <p class="mb-2 text-gray-700">The content has either been moved, deleted, or never even left the runway.</p>
        <p class="mb-4 text-gray-700">We've got clearance, Clarence, but we've lost our vector, Victor.</p>
        <a href="index.php"
            class="bg-blue-600 text-white px-6 py-2 rounded-md font-bold hover:bg-blue-700 transition inline-block"
            style="margin-top: 12px;">
            Return to the Hangar
        </a>
    </div>

</body>

</html>