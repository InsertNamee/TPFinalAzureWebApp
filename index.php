<?php

require 'vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Queue\QueueRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

class ImageUploadSystem {
    private $blobClient;
    private $queueClient;
    private $containerName;
    private $queueName;
    
    public function __construct($connectionString, $containerName, $queueName) {
        $this->blobClient = BlobRestProxy::createBlobService($connectionString);
        $this->queueClient = QueueRestProxy::createQueueService($connectionString);
        $this->containerName = $containerName;
        $this->queueName = $queueName;
        
        // Ensure container and queue exist
        $this->initializeStorage();
    }
    
    private function initializeStorage() {
        try {
            // Create container if it doesn't exist
            $this->blobClient->createContainer($this->containerName);
        } catch (ServiceException $e) {
            // Container might already exist
        }
        
        try {
            // Create queue if it doesn't exist
            $this->queueClient->createQueue($this->queueName);
        } catch (ServiceException $e) {
            // Queue might already exist
        }
    }
    
    public function uploadImage($file, $width, $height) {
        try {
            // Validate file
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                throw new Exception('Invalid file upload');
            }
            
            // Validate image
            $imageInfo = getimagesize($file['tmp_name']);
            if (!$imageInfo) {
                throw new Exception('Invalid image file');
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $blobName = uniqid() . '.' . $extension;
            
            // Read file content
            $content = fopen($file['tmp_name'], 'r');
            
            // Upload to blob storage
            $this->blobClient->createBlockBlob(
                $this->containerName,
                $blobName,
                $content
            );
            
            // Get blob URL
            $blobUrl = $this->blobClient->getBlobUrl(
                $this->containerName,
                $blobName
            );
            
            // Create message for queue
            $message = json_encode([
                'image_path' => $blobUrl,
                'desired_width' => $width,
                'desired_height' => $height,
                'original_filename' => $file['name'],
                'timestamp' => time()
            ]);
            
            // Add message to queue
            $this->queueClient->createMessage($this->queueName, base64_encode($message));
            
            return [
                'success' => true,
                'blob_url' => $blobUrl,
                'message' => 'Image uploaded successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

// Example usage in a form handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Azure Storage connection string and names
    $connectionString = "DefaultEndpointsProtocol=https;AccountName=YOUR_ACCOUNT;AccountKey=YOUR_KEY";
    $containerName = "images";
    $queueName = "image-processing";
    
    // Initialize the upload system
    $uploadSystem = new ImageUploadSystem($connectionString, $containerName, $queueName);
    
    // Handle the upload
    $result = $uploadSystem->uploadImage(
        $_FILES['image'],
        $_POST['width'],
        $_POST['height']
    );
    
    // Output result as JSON
    header('Content-Type: application/json');
    echo json_encode($result);
}
?>