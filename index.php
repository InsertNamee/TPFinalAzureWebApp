<?php
require __DIR__ . '/vendor/autoload.php';

<<<<<<< HEAD
<<<<<<< HEAD
// Chargement des variables d'environnement
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use MicrosoftAzure\Storage\Blob\BlobRestProxy;

class Config {
    const MAX_FILE_SIZE = 5242880; // 5MB
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
    
    // Utilisation des variables d'environnement
    public static function getConnectionString() {
        return $_ENV['AZURE_STORAGE_CONNECTION_STRING'];
    }
    
    public static function getContainerName() {
        return $_ENV['AZURE_CONTAINER_NAME'];
    }
    
    // Utilisation du stockage temporaire d'Azure Web App
    public static function getTempDir() {
        return $_SERVER['TEMP'] ?? sys_get_temp_dir();
    }
}

class ImageProcessor {
    private $sourcePath;
    private $divisionFactor;
    private $blobClient;
    
    public function __construct($sourcePath, $divisionFactor) {
        $this->sourcePath = $sourcePath;
        $this->divisionFactor = max(1, floatval($divisionFactor));
        $this->blobClient = BlobRestProxy::createBlobService(Config::getConnectionString());
    }
    
    public function process() {
        list($origWidth, $origHeight, $type) = getimagesize($this->sourcePath);
        
        $newWidth = round($origWidth / $this->divisionFactor);
        $newHeight = round($origHeight / $this->divisionFactor);
        
        $targetImage = imagecreatetruecolor($newWidth, $newHeight);
        
        if ($type == IMAGETYPE_PNG) {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            imagefilledrectangle($targetImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($this->sourcePath);
                $extension = 'jpg';
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($this->sourcePath);
                $extension = 'png';
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($this->sourcePath);
                $extension = 'gif';
                break;
            default:
                throw new Exception('Format d\'image non supporté');
        }
        
        imagecopyresampled(
            $targetImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $origWidth, $origHeight
        );
        
        // Utiliser un GUID pour le nom du fichier
        $blobName = sprintf(
            '%s_%dx%d.%s',
            bin2hex(random_bytes(16)),
            $newWidth,
            $newHeight,
            $extension
        );
        
        $tempPath = Config::getTempDir() . '/' . $blobName;
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($targetImage, $tempPath, 90);
                break;
            case IMAGETYPE_PNG:
                imagepng($targetImage, $tempPath, 9);
                break;
            case IMAGETYPE_GIF:
                imagegif($targetImage, $tempPath);
                break;
        }
        
        // Upload vers Azure Blob avec gestion des erreurs
        try {
            $content = fopen($tempPath, "r");
            $this->blobClient->createBlockBlob(Config::getContainerName(), $blobName, $content);
            fclose($content);
        } catch (Exception $e) {
            throw new Exception('Erreur lors de l\'upload vers Azure: ' . $e->getMessage());
        } finally {
            // Nettoyage des fichiers temporaires
            if (file_exists($tempPath)) unlink($tempPath);
            if (file_exists($this->sourcePath)) unlink($this->sourcePath);
            imagedestroy($sourceImage);
            imagedestroy($targetImage);
        }
        
        return $this->blobClient->getBlobUrl(Config::getContainerName(), $blobName);
    }
}

// Gestion des erreurs pour production
function handleError($error) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue. Veuillez réessayer plus tard.'
    ]);
    
    // Log l'erreur dans Azure
    error_log(sprintf(
        "Erreur: %s\nFichier: %s\nLigne: %d\nTrace:\n%s",
        $error->getMessage(),
        $error->getFile(),
        $error->getLine(),
        $error->getTraceAsString()
    ));
}

set_exception_handler('handleError');

// Traitement de l'upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Vérification de la validité de la requête
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erreur lors de l\'upload du fichier');
        }
        
        $file = $_FILES['image'];
        
        if ($file['size'] > Config::MAX_FILE_SIZE) {
            throw new Exception('Le fichier est trop volumineux (max 5MB)');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, Config::ALLOWED_TYPES)) {
            throw new Exception('Type de fichier non autorisé (JPG, PNG ou GIF uniquement)');
        }
        
        // Déplacement vers le dossier temporaire
        $tempPath = Config::getTempDir() . '/' . uniqid() . '_' . basename($file['name']);
        if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
            throw new Exception('Erreur lors du traitement du fichier');
        }
        
        $divisionFactor = floatval($_POST['factor'] ?? 1);
        if ($divisionFactor < 1) {
            throw new Exception('Le facteur de division doit être supérieur ou égal à 1');
        }
        
        $processor = new ImageProcessor($tempPath, $divisionFactor);
        $blobUrl = $processor->process();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Image traitée avec succès',
            'url' => $blobUrl
        ]);
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redimensionnement d'Image - Azure Web App</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .form-container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        input {
            padding: 8px;
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        input[type="file"] {
            border: none;
            padding: 0;
        }
        button {
            background-color: #0078D4;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        button:hover {
            background-color: #106EBE;
        }
        #preview {
            margin-top: 20px;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .error { 
            color: #dc3545;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            background-color: #f8d7da;
        }
        .success { 
            color: #28a745;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            background-color: #d4edda;
        }
        .loading {
            text-align: center;
            margin: 20px 0;
            display: none;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Redimensionnement d'Image</h1>
        
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="form-group">
                <label for="image">Sélectionner une image :</label>
                <input type="file" id="image" name="image" accept="image/*" required>
            </div>
            
            <div class="form-group">
                <label for="factor">Facteur de division (ex: 2 pour réduire de moitié) :</label>
                <input type="number" id="factor" name="factor" required min="1" step="0.1" value="1">
            </div>
            
            <button type="submit">Uploader et Redimensionner</button>
        </form>
    </div>
    
    <div class="loading" id="loading">
        Traitement en cours...
    </div>
    
    <div id="message"></div>
    <div id="preview"></div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const messageDiv = document.getElementById('message');
            const previewDiv = document.getElementById('preview');
            const loadingDiv = document.getElementById('loading');
            
            try {
                loadingDiv.style.display = 'block';
                messageDiv.innerHTML = '';
                previewDiv.innerHTML = '';
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    messageDiv.className = 'success';
                    messageDiv.textContent = result.message;
                    
                    previewDiv.innerHTML = `
                        <h3>Image redimensionnée :</h3>
                        <img src="${result.url}" alt="Image redimensionnée" style="max-width: 100%;">
                        <p>URL de l'image : <a href="${result.url}" target="_blank">${result.url}</a></p>
                    `;
                } else {
                    messageDiv.className = 'error';
                    messageDiv.textContent = result.message;
                    previewDiv.innerHTML = '';
                }
            } catch (error) {
                messageDiv.className = 'error';
                messageDiv.textContent = 'Erreur lors de l\'upload. Veuillez réessayer.';
                previewDiv.innerHTML = '';
            } finally {
                loadingDiv.style.display = 'none';
            }
        });
    </script>
</body>
</html>
=======
=======
>>>>>>> parent of 3e967f3 (bhujniklmegtrfhujikolm!edgtrfjiklo)
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
<<<<<<< HEAD
?>
>>>>>>> parent of 3e967f3 (bhujniklmegtrfhujikolm!edgtrfjiklo)
=======
?>
>>>>>>> parent of 3e967f3 (bhujniklmegtrfhujikolm!edgtrfjiklo)
