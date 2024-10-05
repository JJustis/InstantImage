<?php
header('Content-Type: application/json');

// Define file paths for base images
$baseDirectory = 'tmp_images'; // Directory containing base images
$blackImagePath = "$baseDirectory/black.png";
$whiteImagePath = "$baseDirectory/white.png";
$clearImagePath = "$baseDirectory/clear.png";

// Check if base images exist
if (!file_exists($blackImagePath) || !file_exists($whiteImagePath) || !file_exists($clearImagePath)) {
    echo json_encode(['status' => 'error', 'message' => 'Base images not found.']);
    exit;
}

// Get uploaded target image
$targetImagePath = isset($_FILES['targetImage']['tmp_name']) ? $_FILES['targetImage']['tmp_name'] : null;
if (!$targetImagePath) {
    echo json_encode(['status' => 'error', 'message' => 'No target image uploaded.']);
    exit;
}

// Validate the uploaded file to ensure it is a valid PNG image
if (!is_uploaded_file($targetImagePath) || !getimagesize($targetImagePath)) {
    echo json_encode(['status' => 'error', 'message' => 'Uploaded file is not a valid image or not a PNG file.']);
    exit;
}

// Load base images and target image
$blackImage = @imagecreatefrompng($blackImagePath);
$whiteImage = @imagecreatefrompng($whiteImagePath);
$clearImage = @imagecreatefrompng($clearImagePath);
$targetImage = @imagecreatefrompng($targetImagePath);

// Check if images were loaded correctly
if (!$blackImage || !$whiteImage || !$clearImage || !$targetImage) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to load one or more images.']);
    exit;
}

// Get image dimensions (assume all images have the same dimensions)
$imageWidth = imagesx($targetImage);
$imageHeight = imagesy($targetImage);

// If the dimensions are not valid, terminate the process
if ($imageWidth == 0 || $imageHeight == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid image dimensions.']);
    imagedestroy($targetImage);
    exit;
}

// Create a new image for the similarity map
$outputImage = imagecreatetruecolor($imageWidth, $imageHeight);
if (!$outputImage) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create output image.']);
    imagedestroy($targetImage);
    exit;
}

imagealphablending($outputImage, false);
imagesavealpha($outputImage, true);

// Set colors for the output image
$blackColor = imagecolorallocate($outputImage, 0, 0, 0);
$whiteColor = imagecolorallocate($outputImage, 255, 255, 255);
$clearColor = imagecolorallocatealpha($outputImage, 0, 0, 0, 127); // Fully transparent

// Initialize counters for similarity scoring
$totalBlack = 0;
$totalWhite = 0;
$totalClear = 0;

for ($y = 0; $y < $imageHeight; $y++) {
    for ($x = 0; $x < $imageWidth; $x++) {
        // Get RGB values for each base image and the target image
        $blackPixel = imagecolorat($blackImage, $x, $y);
        $whitePixel = imagecolorat($whiteImage, $x, $y);
        $clearPixel = imagecolorat($clearImage, $x, $y);
        $targetPixel = imagecolorat($targetImage, $x, $y);

        $targetRGB = getRGB($targetPixel);
        $blackRGB = getRGB($blackPixel);
        $whiteRGB = getRGB($whitePixel);
        $clearRGB = getRGB($clearPixel);

        // Calculate Euclidean distance to each base image
        $distToBlack = euclideanDistance($targetRGB, $blackRGB);
        $distToWhite = euclideanDistance($targetRGB, $whiteRGB);
        $distToClear = euclideanDistance($targetRGB, $clearRGB);

        // Determine the closest base image and set the output pixel color
        if ($distToBlack < $distToWhite && $distToBlack < $distToClear) {
            $totalBlack++;
            imagesetpixel($outputImage, $x, $y, $blackColor);
        } elseif ($distToWhite < $distToBlack && $distToWhite < $distToClear) {
            $totalWhite++;
            imagesetpixel($outputImage, $x, $y, $whiteColor);
        } else {
            $totalClear++;
            imagesetpixel($outputImage, $x, $y, $clearColor);
        }
    }
}

// Save the output similarity map
$outputFilename = 'similarity_map_' . uniqid() . '.png';
$outputPath = 'tmp_images/' . $outputFilename;
imagepng($outputImage, $outputPath);

// Calculate overall similarity scores
$pixelCount = $imageWidth * $imageHeight;
$similarityScores = [
    'black' => $totalBlack / $pixelCount,
    'white' => $totalWhite / $pixelCount,
    'clear' => $totalClear / $pixelCount
];

// Return the similarity scores and image path
echo json_encode(['status' => 'success', 'similarityScores' => $similarityScores, 'imagePath' => $outputPath]);

// Free image resources
imagedestroy($blackImage);
imagedestroy($whiteImage);
imagedestroy($clearImage);
imagedestroy($targetImage);
imagedestroy($outputImage);

// Helper function to extract RGB values from a pixel
function getRGB($pixel) {
    return [
        'red' => ($pixel >> 16) & 0xFF,
        'green' => ($pixel >> 8) & 0xFF,
        'blue' => $pixel & 0xFF
    ];
}

// Helper function to calculate Euclidean distance between two RGB values
function euclideanDistance($rgb1, $rgb2) {
    return sqrt(pow($rgb1['red'] - $rgb2['red'], 2) + pow($rgb1['green'] - $rgb2['green'], 2) + pow($rgb1['blue'] - $rgb2['blue'], 2));
}
?>
