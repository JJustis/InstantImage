<?php
header('Content-Type: application/json');

// Directory where images are stored
$imageDirectory = 'tmp_images';
$whiteImage = 'white.png'; // Reference image for maximum similarity (all white)
$blackImage = 'black.png'; // Reference image for minimum similarity (all black)
$clearImage = 'clear.png'; // Reference image for clear/transparent similarity

// Check if the directory and reference images exist
if (!file_exists($imageDirectory) || !file_exists("$imageDirectory/$whiteImage") || !file_exists("$imageDirectory/$blackImage") || !file_exists("$imageDirectory/$clearImage")) {
    echo json_encode(['status' => 'error', 'message' => 'Image directory or reference images not found.']);
    exit;
}

// Read all image files from the directory
$images = glob("$imageDirectory/*.png");

// Load reference images and convert to grayscale
$whiteImagePath = "$imageDirectory/$whiteImage";
$blackImagePath = "$imageDirectory/$blackImage";
$clearImagePath = "$imageDirectory/$clearImage";
$whiteImageData = imagecreatefrompng($whiteImagePath);
$blackImageData = imagecreatefrompng($blackImagePath);
$clearImageData = imagecreatefrompng($clearImagePath);
$whiteImageGray = convertToGrayscale($whiteImageData);
$blackImageGray = convertToGrayscale($blackImageData);
$clearImageGray = convertToGrayscale($clearImageData);

$imageData = [];

// Calculate similarity score for each image
foreach ($images as $image) {
    $filename = basename($image);
    $imageDataContent = imagecreatefrompng($image);
    $imageGray = convertToGrayscale($imageDataContent);

    // Calculate similarity to white, black, and clear images
    $similarityToWhite = calculateSimilarity($imageGray, $whiteImageGray);
    $similarityToBlack = calculateSimilarity($imageGray, $blackImageGray);
    $similarityToClear = calculateSimilarity($imageGray, $clearImageGray);

    // Determine the combined similarity score
    $combinedScore = calculateCombinedSimilarity($similarityToWhite, $similarityToBlack, $similarityToClear);

    // Generate a new filename with the combined similarity score appended
    $newFilename = "{$imageDirectory}/img_{$combinedScore}_" . uniqid() . ".png";
    copy($image, $newFilename);

    $imageData[] = [
        'filename' => $newFilename,
        'similarityToWhite' => $similarityToWhite,
        'similarityToBlack' => $similarityToBlack,
        'similarityToClear' => $similarityToClear,
        'combinedScore' => $combinedScore
    ];

    imagedestroy($imageGray);
    imagedestroy($imageDataContent);
}

// Sort images based on combined similarity score
usort($imageData, function ($a, $b) {
    return $b['combinedScore'] <=> $a['combinedScore'];
});

echo json_encode(['status' => 'success', 'images' => $imageData]);

// Helper function to convert an image to grayscale
function convertToGrayscale($image) {
    $width = imagesx($image);
    $height = imagesy($image);
    $grayImage = imagecreatetruecolor($width, $height);
    imagecopy($grayImage, $image, 0, 0, 0, 0, $width, $height);

    // Apply grayscale filter
    imagefilter($grayImage, IMG_FILTER_GRAYSCALE);

    return $grayImage;
}

// Helper function to calculate the similarity between two grayscale images
function calculateSimilarity($image1, $image2) {
    $width = imagesx($image1);
    $height = imagesy($image1);
    $totalPixels = $width * $height;
    $similaritySum = 0;

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $gray1 = imagecolorat($image1, $x, $y) & 0xFF;
            $gray2 = imagecolorat($image2, $x, $y) & 0xFF;
            $similaritySum += 1 - abs($gray1 - $gray2) / 255; // Normalize difference to a value between 0 and 1
        }
    }

    return $similaritySum / $totalPixels; // Average similarity across all pixels
}

// Helper function to calculate the combined similarity score using the three base images
function calculateCombinedSimilarity($similarityToWhite, $similarityToBlack, $similarityToClear) {
    // Calculate weightings based on closeness to each reference image
    $weightWhite = $similarityToWhite;
    $weightBlack = $similarityToBlack;
    $weightClear = $similarityToClear;

    // Calculate the combined score as a weighted sum
    $combinedScore = ($weightWhite + $weightBlack + $weightClear) / 3;

    return number_format($combinedScore, 7); // Return combined score formatted to 7 decimal places
}
?>
