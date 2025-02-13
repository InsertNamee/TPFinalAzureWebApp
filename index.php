<?php
// config.php
class Config {
    const UPLOAD_DIR = 'uploads/';
    const MAX_FILE_SIZE = 5242880; // 5MB
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
}

// ImageProcessor.php
class ImageProcessor {
    private $sourcePath;
    private $targetWidth;
    private $targetHeight;
    
    public function __construct($sourcePath, $width, $height) {
        $this->sourcePath = $sourcePath;
        $this->targetWidth = $width;
        $this->targetHeight = $height;
    }
    
    public function resize() {
        list($origWidth, $origHeight, $type) = getimagesize($this->sourcePath);
        
        // Créer une nouvelle image
        $targetImage = imagecreatetruecolor($this->targetWidth, $this->targetHeight);
        
        // Gestion de la transparence pour PNG
        if ($type == IMAGETYPE_PNG) {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            imagefilledrectangle($targetImage, 0, 0, $this->targetWidth, $this->targetHeight, $transparent);
        }
        
        // Charger l'image source
        switch ($type) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($this->sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($this->sourcePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($this->sourcePath);
                break;
            default:
                throw new Exception('Format d\'image non supporté');
        }
        
        // Redimensionner
        imagecopyresampled(
            $targetImage, $sourceImage,
            0, 0, 0, 0,
            $this->targetWidth, $this->targetHeight,
            $origWidth, $origHeight
        );
        
        // Générer le nouveau nom de fichier
        $pathInfo = pathinfo($this->sourcePath);
        $newFilename = $pathInfo['filename'] . '_' . $this->targetWidth . 'x' . $this->targetHeight . '.' . $pathInfo['extension'];
        $newPath = Config::UPLOAD_DIR . $newFilename;
        
        // Sauvegarder la nouvelle image
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($targetImage, $newPath, 90);
                break;
            case IMAGETYPE_PNG:
                imagepng($targetImage, $newPath, 9);
                break;
            case IMAGETYPE_GIF:
                imagegif($targetImage, $newPath);
                break;
        }
        
        // Libérer la mémoire
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        
        return $newPath;
    }
}

// upload.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => '', 'path' => ''];
    
    try {
        // Vérifier si un fichier a été uploadé
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erreur lors de l\'upload du fichier');
        }
        
        $file = $_FILES['image'];
        
        // Vérifier la taille
        if ($file['size'] > Config::MAX_FILE_SIZE) {
            throw new Exception('Le fichier est trop volumineux');
        }
        
        // Vérifier le type MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, Config::ALLOWED_TYPES)) {
            throw new Exception('Type de fichier non autorisé');
        }
        
        // Créer le dossier d'upload si nécessaire
        if (!file_exists(Config::UPLOAD_DIR)) {
            mkdir(Config::UPLOAD_DIR, 0777, true);
        }
        
        // Générer un nom unique
        $filename = uniqid() . '_' . basename($file['name']);
        $uploadPath = Config::UPLOAD_DIR . $filename;
        
        // Déplacer le fichier
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Erreur lors du déplacement du fichier');
        }
        
        // Redimensionner l'image
        $width = intval($_POST['width']);
        $height = intval($_POST['height']);
        
        if ($width <= 0 || $height <= 0) {
            throw new Exception('Dimensions invalides');
        }
        
        $processor = new ImageProcessor($uploadPath, $width, $height);
        $resizedPath = $processor->resize();
        
        // Supprimer l'original si souhaité
        unlink($uploadPath);
        
        $response['success'] = true;
        $response['message'] = 'Image traitée avec succès';
        $response['path'] = $resizedPath;
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload et Redimensionnement d'Image</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input {
            padding: 8px;
            width: 100%;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        #preview {
            margin-top: 20px;
        }
        .error {
            color: red;
            margin-top: 10px;
        }
        .success {
            color: green;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h1>Upload et Redimensionnement d'Image</h1>
    
    <form id="uploadForm" enctype="multipart/form-data">
        <div class="form-group">
            <label for="image">Sélectionner une image :</label>
            <input type="file" id="image" name="image" accept="image/*" required>
        </div>
        
        <div class="form-group">
            <label for="width">Largeur (en pixels) :</label>
            <input type="number" id="width" name="width" required min="1" max="2000">
        </div>
        
        <div class="form-group">
            <label for="height">Hauteur (en pixels) :</label>
            <input type="number" id="height" name="height" required min="1" max="2000">
        </div>
        
        <button type="submit">Uploader et Redimensionner</button>
    </form>
    
    <div id="message"></div>
    <div id="preview"></div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const messageDiv = document.getElementById('message');
            const previewDiv = document.getElementById('preview');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    messageDiv.className = 'success';
                    messageDiv.textContent = result.message;
                    
                    // Afficher l'image redimensionnée
                    previewDiv.innerHTML = `
                        <h3>Image redimensionnée :</h3>
                        <img src="${result.path}" alt="Image redimensionnée">
                    `;
                } else {
                    messageDiv.className = 'error';
                    messageDiv.textContent = result.message;
                    previewDiv.innerHTML = '';
                }
            } catch (error) {
                messageDiv.className = 'error';
                messageDiv.textContent = 'Erreur lors de l\'upload';
                previewDiv.innerHTML = '';
            }
        });
    </script>
</body>
</html>