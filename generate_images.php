<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$suggestions_file = "suggestions.txt"; 
$word_vectors_file = "word_vectors.json"; 
$related_words_file = "related_words.json"; 
$mega_image_file = "mega_image.png"; 
$live_image_file = "live_image.png"; 
$directory = "tmp_images"; 

$keywords_time_frame = 30;

// Create directories if they don't exist
if (!file_exists($directory)) {
    mkdir($directory, 0777, true);
}

// Load pre-trained word vectors
$word_vectors = loadWordVectors($word_vectors_file);

// Generate the Mega Image of all Suggestions
generateMegaImage($suggestions_file, $related_words_file, $mega_image_file, $directory);

// Generate the Live Image of Suggestions in the Past 30 Seconds
generateLiveImage($suggestions_file, $related_words_file, $live_image_file, $directory, $keywords_time_frame);

// Scrape Images for Each Keyword and Generate an Image Grid
generateKeywordImageGrid($suggestions_file, $directory);

function generateMegaImage($suggestions_file, $related_words_file, $output_file, $directory) {
    global $word_vectors;

    $suggestions = file_exists($suggestions_file) ? file($suggestions_file, FILE_IGNORE_NEW_LINES) : [];
    $relatedWords = [];

    $columns = 5;
    $width = 200;
    $height = 200;
    $spacing = 20;
    $rows = ceil(count($suggestions) / $columns);
    $canvas_width = $columns * ($width + $spacing) + $spacing;
    $canvas_height = $rows * ($height + $spacing) + $spacing;
    $canvas = imagecreatetruecolor($canvas_width, $canvas_height);

    $background_color = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $background_color);

    $x = $spacing;
    $y = $spacing;

    foreach ($suggestions as $index => $keyword) {
        $definition = getDefinitionFromDatabase($keyword); // Retrieve the definition
        if (!$definition) {
            continue; // Skip if no definition found
        }

        // Calculate related words and their opacities using the definition
        $relatedWords = analyzeTextAndGetRelatedWords($keyword, $definition); // Pass both arguments

        // Create a placeholder image for the keyword and related words
        $word_image = createKeywordImage($keyword, $relatedWords, $width, $height, $directory);

        $keyword_image = imagecreatefrompng($word_image);
        imagecopy($canvas, $keyword_image, $x, $y, 0, 0, $width, $height);
        imagedestroy($keyword_image);

        $x += $width + $spacing;
        if (($index + 1) % $columns == 0) {
            $x = $spacing;
            $y += $height + $spacing;
        }
    }

    imagepng($canvas, $output_file);
    imagedestroy($canvas);
}

// Function to generate a live image of suggestions in the past X seconds
function generateLiveImage($suggestions_file, $related_words_file, $output_file, $directory, $time_frame) {
    $suggestions = file_exists($suggestions_file) ? file($suggestions_file, FILE_IGNORE_NEW_LINES) : [];
    $recentSuggestions = [];

    // Filter suggestions from the last X seconds
    $currentTime = time();
    foreach ($suggestions as $suggestion) {
        $parts = explode(";", $suggestion);
        if (count($parts) == 2 && ($currentTime - intval($parts[1])) <= $time_frame) {
            $recentSuggestions[] = $parts[0];
        }
    }

    // Generate the live image using the same logic as the mega image
    generateMegaImageFromArray($recentSuggestions, $output_file, $directory);
}

// Function to generate a mega image from an array of suggestions
function generateMegaImageFromArray($suggestions, $output_file, $directory) {
    global $word_vectors;

    $columns = 5;
    $width = 200;
    $height = 200;
    $spacing = 20;
    $rows = ceil(count($suggestions) / $columns);
    $canvas_width = $columns * ($width + $spacing) + $spacing;
    $canvas_height = $rows * ($height + $spacing) + $spacing;
    $canvas = imagecreatetruecolor($canvas_width, $canvas_height);

    // Fill the canvas with a white background
    $background_color = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $background_color);

    $x = $spacing;
    $y = $spacing;

    // Place each keyword on the grid
    foreach ($suggestions as $index => $keyword) {
        // Calculate related words and their opacities
        $relatedWords = analyzeTextAndGetRelatedWords($keyword);

        // Create a placeholder image for the keyword and related words
        $word_image = createKeywordImage($keyword, $relatedWords, $width, $height, $directory);

        // Add the image to the canvas
        $keyword_image = imagecreatefrompng($word_image);
        imagecopy($canvas, $keyword_image, $x, $y, 0, 0, $width, $height);
        imagedestroy($keyword_image);

        // Move to the next position
        $x += $width + $spacing;
        if (($index + 1) % $columns == 0) {
            $x = $spacing;
            $y += $height + $spacing;
        }
    }

    // Save the final image
    imagepng($canvas, $output_file);
    imagedestroy($canvas);
}

// Function to create a keyword image with related words placed around it
function createKeywordImage($keyword, $relatedWords, $width, $height, $directory) {
    $image = imagecreatetruecolor($width, $height);
    $background_color = imagecolorallocate($image, 255, 255, 255); // White background
    $text_color = imagecolorallocate($image, 0, 0, 0); // Black text
    imagefill($image, 0, 0, $background_color);

    // Place the keyword in the center
    $font_size = 5;
    $text_x = ($width / 2) - (imagefontwidth($font_size) * strlen($keyword) / 2);
    $text_y = ($height / 2) - (imagefontheight($font_size) / 2);
    imagestring($image, $font_size, $text_x, $text_y, $keyword, $text_color);

    // Place related words around the keyword based on their opacity
    foreach ($relatedWords as $relatedWord) {
        $opacity = 127 - intval($relatedWord['opacity'] * 127); // Convert opacity to range 0-127
        $related_color = imagecolorallocatealpha($image, 0, 0, 0, $opacity);
        $related_x = rand(0, $width - imagefontwidth($font_size) * strlen($relatedWord['word']));
        $related_y = rand(0, $height - imagefontheight($font_size));
        imagestring($image, $font_size, $related_x, $related_y, $relatedWord['word'], $related_color);
    }

    $filename = "$directory/" . md5($keyword) . ".png";
    imagepng($image, $filename);
    imagedestroy($image);

    return $filename;
}
