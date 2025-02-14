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

/*$blobClient = BlobRestProxy::createBlobService($blobConnectionString);
$queueClient = QueueRestProxy::createQueueService($queueConnectionString);

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
            $queueClient->createMessage($queueName, $blobUrl . "," . $dimensions);

            echo "File uploaded and message sent to queue";
        } catch (ServiceException $e) {
            echo "Exception: " . $e->getMessage();
        }
    } else {
        echo "No file or dimensions provided";
    }
}*/
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Image</title>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f4f4;
            margin: 0;
        }
        .upload-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-align: center;
            width: 350px;
        }
        .upload-container h1 {
            color: #333;
        }
        .upload-container label {
            display: block;
            background: #007BFF;
            color: white;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 0;
        }
        .upload-container input[type="file"] {
            display: none;
        }
        .upload-container input[type="text"],
        .upload-container input[type="submit"] {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .upload-container input[type="submit"] {
            background: #007BFF;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
        .upload-container input[type="submit"]:hover {
            background: #0056b3;
        }
        .icon {
            font-size: 50px;
            color: #007BFF;
            margin-bottom: 10px;
        }
        #file-name {
            margin-top: 10px;
            color: #333;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="upload-container">
        <i class="fas fa-cloud-upload-alt icon"></i>
        <h1>Upload Image</h1>
        <form method="post" enctype="multipart/form-data">
            <label for="file-upload"><i class="fas fa-file-upload"></i> Choisir une image</label>
            <input type="file" id="file-upload" name="image" accept="image/*" required onchange="displayFileName(this)">
            <p id="file-name">Aucun fichier sélectionné</p>
            <input type="text" name="dimensions" placeholder="Dimension divisée par" required>
            <input type="submit" value="Upload">
        </form>
    </div>

    <script>
        function displayFileName(input) {
            if (input.files.length > 0) {
                document.getElementById("file-name").textContent = "Fichier : " + input.files[0].name;
            }
