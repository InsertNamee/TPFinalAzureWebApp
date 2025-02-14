<?php
require 'vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Queue\QueueRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
    // Required environment variables
    $dotenv->required(['AZURE_STORAGE_ACCOUNT', 'AZURE_STORAGE_KEY', 'AZURE_BLOB_CONTAINER', 'AZURE_QUEUE_NAME'])->notEmpty();
}

// Azure Configuration using environment variables
$accountName = $_ENV['AZURE_STORAGE_ACCOUNT'];
$accountKey = $_ENV['AZURE_STORAGE_KEY'];
$blobConnectionString = "DefaultEndpointsProtocol=https;AccountName=" . $accountName . 
                       ";AccountKey=" . $accountKey . 
                       ";EndpointSuffix=core.windows.net";
$blobContainerName = $_ENV['AZURE_BLOB_CONTAINER'];

// Queue configuration
$queueConnectionString = $blobConnectionString; // Reuse the same connection string
$queueName = $_ENV['AZURE_QUEUE_NAME'];

try {
    // Rest of your existing code remains the same
    $blobClient = BlobRestProxy::createBlobService($blobConnectionString);
    $queueClient = QueueRestProxy::createQueueService($queueConnectionString);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // ... [rest of your existing code remains unchanged]
    }
} catch (ServiceException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Azure service error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Image</title>
</head>
<body>
    <h1>Upload Image</h1>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="image" accept="image/*" required>
        <input type="text" name="dimensions" placeholder="dimension divisée par" required>
        <input type="submit" value="Upload">
    </form>
</body>
</html>