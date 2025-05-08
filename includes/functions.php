<?php
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function uploadImage($file, $target_dir) {
    // Create target directory if it doesn't exist
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory.'];
        }
    }

    // Verify directory is writable
    if (!is_writable($target_dir)) {
        return ['success' => false, 'message' => 'Upload directory is not writable.'];
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        return ['success' => false, 'message' => $upload_errors[$file['error']] ?? 'Unknown upload error'];
    }

    // Check if image is real
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return ['success' => false, 'message' => 'File is not an image.'];
    }

    // File size check (max 5MB)
    if ($file["size"] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File is too large. Max 5MB.'];
    }

    // Allowed types with proper MIME validation
    $allowedTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];
    
    $file_ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $file_mime = mime_content_type($file["tmp_name"]);
    
    if (!array_key_exists($file_ext, $allowedTypes) || 
        $allowedTypes[$file_ext] !== $file_mime) {
        return ['success' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed.'];
    }

    // Generate secure filename
    $new_filename = bin2hex(random_bytes(16)) . '.' . $file_ext;
    $target_path = $target_dir . $new_filename;

    if (move_uploaded_file($file["tmp_name"], $target_path)) {
        // Verify the file was actually moved
        if (file_exists($target_path)) {
            return ['success' => true, 'filename' => $new_filename];
        } else {
            return ['success' => false, 'message' => 'File upload verification failed.'];
        }
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file.'];
    }
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos(min(max($dist, -1.0), 1.0));
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    return $miles * 1.609344;
}
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>

