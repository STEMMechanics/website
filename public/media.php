<?php
if (isset($_GET['url']) && strpos($_GET['url'], 'uploads/') === 0 && is_file($_GET['url'])) {
    $image = imagecreatefromstring(file_get_contents($_GET['url']));

    $newWidth = (isset($_GET['w']) ? intval($_GET['w']) : -1);
    $newHeight = (isset($_GET['h']) ? intval($_GET['h']) : -1);

    if($newWidth != -1 || $newHeight != -1) {
        $width = imagesx($image);
        $height = imagesy($image);

        $aspectRatio = $width / $height;

        if($newWidth == -1) {
            $newWidth = intval($newHeight * $aspectRatio);
        }

        if($newHeight == -1) {
            $newHeight = intval($newWidth / $aspectRatio);
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Output the resized image to the browser
        $mime_type = mime_content_type($_GET['url']);
        header('Content-Type: ' . $mime_type);
        switch($mime_type) {
            case "image/jpeg":
                imagejpeg($newImage);
                break;
            case "image/gif":
                imagegif($newImage);
                break;
            case "image/png":
                imagepng($newImage);
                break;
        }
        imagedestroy($newImage);
    } else {
        // Output the original image to the browser
        header('Content-Type:  '. mime_content_type($_GET['url']));
        readfile($_GET['url']);
    }

    // Clean up the image resources
    imagedestroy($image);
} else {
    // Return a 404 error
    header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
    exit;
}
