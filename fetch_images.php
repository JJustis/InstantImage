<?php
header('Content-Type: application/json');

// Directory where images are stored
$imageDirectory = 'tmp_images';

// Check if the directory exists
if (!file_exists($imageDirectory)) {
    echo json_encode(['status' => 'error', 'message' => 'Image directory does not exist.']);
    exit;
}

// Read all image files from the directory
$images = glob("$imageDirectory/*.png");

$imageData = [];

// Parse each image filename to extract its unique ID and similarity score
foreach ($images as $image) {
    $filename = basename($image);

    // Use regular expression to extract unique ID and similarity score from the filename
    if (preg_match('/img_(\d+)_similarity_([\d.]+)\.png/', $filename, $matches)) {
        $uniqueID = intval($matches[1]);
        $similarityScore = floatval($matches[2]);
        $imageData[] = [
            'filename' => $filename,
            'uniqueID' => $uniqueID,
            'similarityScore' => $similarityScore
        ];
    }
}

// Sort images by similarity score and then by unique ID (chronological order)
usort($imageData, function ($a, $b) {
    if ($a['similarityScore'] == $b['similarityScore']) {
        return $a['uniqueID'] <=> $b['uniqueID'];
    }
    return $a['similarityScore'] <=> $b['similarityScore'];
});

echo json_encode(['status' => 'success', 'images' => $imageData]);
?>
