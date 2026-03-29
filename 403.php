<?php
// Include config to get $base_url
$config = include __DIR__ . '/config.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>403 - No Peeking!</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-start justify-center h-screen font-sans pt-20">

    <div class="bg-white p-6 rounded-lg shadow-lg text-center max-w-2xl">
        <h1 class="text-2xl font-bold mb-4">403 - No Peeking!</h1>
        <p class="mb-2 font-bold">Hey there… this area is off-limits.</p>
        <p class="mb-2 text-gray-700">Trying to sneak a peek? Not happening.</p>
        <p class="mb-4 text-gray-700">Stick to the permitted zones, or you might ruffle some feathers.</p>
        <a href="<?php echo $config['rss']['base_url']; ?>/index.php"
            class="bg-blue-600 text-white px-6 py-2 rounded-md font-bold hover:bg-blue-700 transition inline-block"
            style="margin-top: 12px;">
            Back to Safety
        </a>
    </div>

</body>

</html>