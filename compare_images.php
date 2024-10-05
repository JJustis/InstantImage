<?php
header('Content-Type: application/json');

$directory = 'tmp_images';
$outputFile = 'similarity_composite_grid.png';

// Check if the directory exists
if (!file_exists($directory)) {
    echo json_encode(['status' => 'error', 'message' => 'Directory not found.']);
    exit;
}

// Get all image files in the directory
$images = glob("$directory/*.png");

if (empty($images)) {
    echo json_encode(['status' => 'error', 'message' => 'No images found in the directory.']);
    exit;
}

// Calculate similarity scores between all pairs of images
$similarityMatrix = calculateImageSimilarityMatrix($images);

// Sort images based on their similarity scores
$sortedImages = sortImagesBySimilarity($similarityMatrix, $images);

// Generate composite image
$compositeImagePath = generateCompositeImage($sortedImages, $outputFile);

echo json_encode(['status' => 'success', 'compositeImage' => $compositeImagePath]);

// Function to calculate the similarity matrix for all images
function calculateImageSimilarityMatrix($images) {
    $matrix = [];

    // Calculate similarity for each pair of images
    for ($i = 0; $i < count($images); $i++) {
        for ($j = $i + 1; $j < count($images); $j++) {
            $similarity = compareImages($images[$i], $images[$j]);
            $matrix[$images[$i]][$images[$j]] = $similarity;
            $matrix[$images[$j]][$images[$i]] = $similarity; // Symmetric matrix
        }
    }

    return $matrix;
}

// Function to compare two images and return a similarity score
function compareImages($imagePathA, $imagePathB) {
    $imageA = @imagecreatefrompng($imagePathA);
    $imageB = @imagecreatefrompng($imagePathB);

    if (!$imageA || !$imageB) {
        return 0; // Return zero similarity if either image is not valid
    }

    // Ensure the images have the same dimensions
    $width = imagesx($imageA);
    $height = imagesy($imageA);

    if ($width != imagesx($imageB) || $height != imagesy($imageB)) {
        return 0; // Return zero similarity for different-sized images
    }

    $similarity = 0;

    // Calculate mean squared error (MSE) between the two images
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $pixelA = imagecolorat($imageA, $x, $y);
            $pixelB = imagecolorat($imageB, $x, $y);

            $rgbA = getRGB($pixelA);
            $rgbB = getRGB($pixelB);

            $similarity += pow($rgbA['red'] - $rgbB['red'], 2) +
                           pow($rgbA['green'] - $rgbB['green'], 2) +
                           pow($rgbA['blue'] - $rgbB['blue'], 2);
        }
    }

    // Normalize similarity score
    $similarity = sqrt($similarity) / ($width * $height);

    // Invert similarity score to represent closeness (lower MSE = higher similarity)
    $normalizedSimilarity = 1 / (1 + $similarity);

    // Free image resources
    imagedestroy($imageA);
    imagedestroy($imageB);

    return $normalizedSimilarity;
}

// Function to sort images based on similarity scores
function sortImagesBySimilarity($matrix, $images) {
    $averageScores = [];

    // Calculate average similarity score for each image
    foreach ($images as $image) {
        $totalSimilarity = 0;
        $count = 0;

        foreach ($matrix[$image] as $otherImage => $score) {
            $totalSimilarity += $score;
            $count++;
        }

        $averageScores[$image] = $totalSimilarity / $count;
    }

    // Sort images based on average similarity score
    asort($averageScores);
    return array_keys($averageScores);
}

// Function to generate composite image
function generateCompositeImage($images, $outputFile) {
    $columns = ceil(sqrt(count($images))); // Determine grid size based on number of images
    $cellSize = 200; // Set each cell size in the grid
    $spacing = 10; // Space between images
    $width = $columns * ($cellSize + $spacing) + $spacing;
    $height = ceil(count($images) / $columns) * ($cellSize + $spacing) + $spacing;

    // Create composite image
    $composite = imagecreatetruecolor($width, $height);
    $backgroundColor = imagecolorallocate($composite, 255, 255, 255);
    imagefill($composite, 0, 0, $backgroundColor);

    $x = $spacing;
    $y = $spacing;

    foreach ($images as $imagePath) {
        $image = imagecreatefrompng($imagePath);

        // Resize and place image in the composite
        $resizedImage = imagecreatetruecolor($cellSize, $cellSize);
        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $cellSize, $cellSize, imagesx($image), imagesy($image));

        imagecopy($composite, $resizedImage, $x, $y, 0, 0, $cellSize, $cellSize);
        imagedestroy($resizedImage);
        imagedestroy($image);

        // Update position
        $x += $cellSize + $spacing;
        if ($x >= $width - $cellSize) {
            $x = $spacing;
            $y += $cellSize + $spacing;
        }
    }

    // Save composite image
    $outputPath = "$directory/$outputFile";
    imagepng($composite, $outputPath);
    imagedestroy($composite);

    return $outputPath;
}

// Helper function to extract RGB values from a pixel
function getRGB($pixel) {
    return [
        'red' => ($pixel >> 16) & 0xFF,
        'green' => ($pixel >> 8) & 0xFF,
        'blue' => $pixel & 0xFF
    ];
}
?>
