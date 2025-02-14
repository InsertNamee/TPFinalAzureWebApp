<?php
require 'vendor/autoload.php';
$config = require 'config.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Queue\QueueRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

$blobClient = BlobRestProxy::createBlobService($config['azure_storage_connection_string']);
/*$queueClient = QueueRestProxy::createQueueService($config['azure_storage_connection_string']);*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle file upload
        if (isset($_FILES['image']) && isset($_POST['dimensions'])) {
            $file = $_FILES['image'];
            $dimensions = $_POST['dimensions'];
            
            // Générer un nom unique et l'envoyer dans le dossier 'ssrc' du Blob Storage
            $blobName = 'ssrc/' . uniqid() . '_' . $file['name'];
            $localPath = __DIR__ . '/src/' . basename($blobName); // Stockage temporaire

            if (move_uploaded_file($file['tmp_name'], $localPath)) {
                // Ouvrir le fichier pour l'upload sur Azure Blob Storage
                $content = fopen($localPath, 'r');
                $blobClient->createBlockBlob(
                    $config['azure_storage_container'],
                    $blobName,
                    $content
                );
                fclose($content);

                // Supprimer le fichier local après upload
                unlink($localPath);
            } else {
                echo "Erreur lors du déplacement du fichier.";
            }
            
            // Create message for queue
            $message = json_encode([
                'blob_name' => $blobName,
                'dimensions' => $dimensions,
                'timestamp' => time()
            ]);
            
            // Add to queue
            //$queueClient->createMessage($config['azure_queue_name'], $message);
            
            $success = "Image uploaded successfully to 'ssrc/' and queued for processing!";
        }
    } catch (ServiceException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>Image Upload and Processor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .form-group { margin-bottom: 20px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background-color: #dff0d8; color: #3c763d; }
        .alert-danger { background-color: #f2dede; color: #a94442; }
    </style>
</head>
<body>
    <h1>Image Upload and Processor</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="image">Select Image:</label><br>
            <input type="file" name="image" id="image" accept="image/*" required>
        </div>
        
        <div class="form-group">
            <label for="dimensions">Select Dimensions:</label><br>
            <select name="dimensions" id="dimensions" required>
                <option value="100x100">100x100</option>
                <option value="200x200">200x200</option>
                <option value="300x300">300x300</option>
                <option value="custom">Custom</option>
            </select>
        </div>
        
        <div class="form-group">
            <input type="submit" value="Upload and Process">
        </div>
    </form>
</body>
</html>