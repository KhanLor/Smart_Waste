<?php
require_once '../config/config.php';
require_login();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'debug' => ''];

try {
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        throw new Exception('User not authenticated');
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['profile_image'])) {
        throw new Exception('No file uploaded - profile_image field missing');
    }
    
    if ($_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file selected');
    }
    
    $file = $_FILES['profile_image'];
    
    // Check for upload errors with detailed messages
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error: ' . $file['error'];
        throw new Exception($errorMsg);
    }
    
    if ($file['size'] === 0) {
        throw new Exception('File is empty (0 bytes)');
    }
    
    // Validate file size (max 5MB)
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxFileSize) {
        throw new Exception('File size exceeds 5MB limit');
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type: ' . $mimeType . '. Only JPG, PNG, GIF, and WebP images are allowed');
    }
    
    // Validate image dimensions (ensure it's an actual image)
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception('File is not a valid image');
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../uploads/profiles/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        throw new Exception('Upload directory is not writable: ' . $uploadDir);
    }
    
    // Get current profile image to delete it later
    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $oldImage = $result->fetch_assoc()['profile_image'] ?? null;
    $stmt->close();
    
    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (empty($extension)) {
        // Fallback to mime type
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        $extension = $mimeMap[$mimeType] ?? 'jpg';
    }
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to move uploaded file to: ' . $targetPath);
    }
    
    // Verify file was created
    if (!file_exists($targetPath)) {
        throw new Exception('File was not created at: ' . $targetPath);
    }
    
    // Resize/optimize image
    try {
            if (extension_loaded('gd')) {
                optimizeProfileImage($targetPath, $mimeType);
            } else {
                $response['debug'] = 'GD extension not available - image uploaded without optimization';
            }
    } catch (Exception $e) {
        // Continue even if optimization fails
        error_log('Image optimization failed: ' . $e->getMessage());
        $response['debug'] = 'Optimization warning: ' . $e->getMessage();
    }
    
    // Update database
    $relativePath = 'uploads/profiles/' . $filename;
    $stmt = $conn->prepare("UPDATE users SET profile_image = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    if (!$stmt) {
        // Delete uploaded file if database prepare fails
        unlink($targetPath);
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('si', $relativePath, $user_id);
    
    if (!$stmt->execute()) {
        // Delete uploaded file if database update fails
        unlink($targetPath);
        throw new Exception('Failed to update database: ' . $stmt->error);
    }
    $stmt->close();
    
    // Delete old image file if it exists
    if ($oldImage && file_exists(__DIR__ . '/../' . $oldImage)) {
        @unlink(__DIR__ . '/../' . $oldImage);
    }
    
    $response['success'] = true;
    $response['message'] = 'Profile image updated successfully';
    $response['image_url'] = BASE_URL . $relativePath;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Profile image upload error: ' . $e->getMessage());
} catch (Error $e) {
    $response['message'] = 'System error: ' . $e->getMessage();
    error_log('Profile image upload system error: ' . $e->getMessage());
}

echo json_encode($response);

/**
 * Optimize profile image - resize and compress
 */
function optimizeProfileImage($imagePath, $mimeType) {
        // Check if GD extension is loaded
        if (!extension_loaded('gd')) {
            throw new Exception('GD extension is not loaded - cannot optimize image');
        }
    
    $maxWidth = 400;
    $maxHeight = 400;
    $quality = 85;
    
    // Load image based on type
    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = @imagecreatefromjpeg($imagePath);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($imagePath);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($imagePath);
            break;
        case 'image/webp':
            $image = @imagecreatefromwebp($imagePath);
            break;
        default:
            throw new Exception('Unsupported image type for optimization: ' . $mimeType);
    }
    
    if (!$image) {
        throw new Exception('Failed to load image for optimization');
    }
    
    // Get current dimensions
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Calculate new dimensions maintaining aspect ratio
    if ($width > $maxWidth || $height > $maxHeight) {
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);
        
        // Create new image
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        if (!$newImage) {
            imagedestroy($image);
            throw new Exception('Failed to create new image for resizing');
        }
        
        // Preserve transparency for PNG and GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save optimized image
        $saved = false;
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                $saved = imagejpeg($newImage, $imagePath, $quality);
                break;
            case 'image/png':
                $saved = imagepng($newImage, $imagePath, (int)(9 - ($quality / 10)));
                break;
            case 'image/gif':
                $saved = imagegif($newImage, $imagePath);
                break;
            case 'image/webp':
                $saved = imagewebp($newImage, $imagePath, $quality);
                break;
        }
        
        imagedestroy($newImage);
        imagedestroy($image);
        
        if (!$saved) {
            throw new Exception('Failed to save optimized image');
        }
    } else {
        imagedestroy($image);
    }
}
