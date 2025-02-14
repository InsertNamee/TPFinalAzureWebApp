<?php
require 'vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Queue\QueueRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

// Configuration Azure
$blobConnectionString = "https://storageaccounttpfinale.blob.core.windows.net/blobimages";
$blobContainerName = "blobimages";
$queueConnectionString = "your_queue_connection_string";
$queueName = "queuestoragedocker ";

$blobClient = BlobRestProxy::createBlobService($blobConnectionString);
//$queueClient = QueueRestProxy::createQueueService($queueConnectionString);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file']) && isset($_POST['dimensions'])) {
        $file = $_FILES['file'];
        $dimensions = $_POST['dimensions'];

        $fileName = $file['name'];
        $fileTempPath = $file['tmp_name'];

        try {
            // Upload the file to Blob Storage
            $blobClient->createBlockBlob($blobContainerName, $fileName, fopen($fileTempPath, 'r'));

            // Get the URL of the uploaded file
            $blobUrl = $blobClient->getBlobUrl($blobContainerName, $fileName);

            // Add message to Queue
            //$queueClient->createMessage($queueName, $blobUrl . "," . $dimensions);

            echo "File uploaded and message sent to queue";
        } catch (ServiceException $e) {
            echo "Exception: " . $e->getMessage();
        }
    } else {
        echo "No file or dimensions provided";
    }
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