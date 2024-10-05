<?php
session_start();
header('Content-Type: application/json'); // Set the correct content-type header

$suggestions_file = "suggestions.txt";
$all_suggestions_file = "all_suggestions.txt"; // Stores all historical suggestions
$image_directory = "tmp_images"; // Directory to store temporary images
$related_words_file = "related_words.json"; // Stores related words and their opacity
$word_vectors_file = "word_vectors.json"; // File containing pre-trained word vectors (optional)
//require_once 'generate_images.php';
// Database configuration
$database_host = 'localhost';
$database_user = 'root';
$database_password = '';
$database_name = 'reservesphp'; 

// Load pre-trained word vectors from file (optional)
$word_vectors = loadWordVectors($word_vectors_file);

// Handle incoming GET request to retrieve suggestions and related words
if (isset($_GET['getSuggestions'])) {
    $suggestions = file_exists($suggestions_file) ? file($suggestions_file, FILE_IGNORE_NEW_LINES) : [];
    $relatedWords = file_exists($related_words_file) ? json_decode(file_get_contents($related_words_file), true) : [];

    echo json_encode([
        'status' => 'success',
        'suggestions' => $suggestions,
        'relatedWords' => $relatedWords
    ]);
    exit;
}

// Handle incoming suggestions and image generation
if (isset($_POST['userInput'])) {
    $input = trim($_POST['userInput']);
    if (!empty($input)) {
        // Save suggestion to the file and historical log
        file_put_contents($suggestions_file, $input . PHP_EOL, FILE_APPEND | LOCK_EX);
        file_put_contents($all_suggestions_file, $input . PHP_EOL, FILE_APPEND | LOCK_EX);

        // Retrieve the definition of the keyword from the database
        $definition = getDefinitionFromDatabase($input);
        if ($definition) {
            // Analyze the definition to find related words
            $relatedWords = analyzeTextAndGetRelatedWords($input, $definition);
            file_put_contents($related_words_file, json_encode($relatedWords)); // Save related words for later use

            // Generate image for this keyword and related words
            $image_path = createSwirlPattern($input, $relatedWords, 500, 500, $image_directory);

            echo json_encode(['status' => 'success', 'image_path' => $image_path, 'message' => 'Image created successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Keyword not found in the database']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Empty input received']);
    }
    exit;
}

// Handle AJAX request to get all recent images
if (isset($_GET['getImages'])) {
    $images = [];
    $image_files = glob($image_directory . '/*.png');
    foreach ($image_files as $file) {
        $images[] = basename($file);
    }
    echo json_encode(['status' => 'success', 'images' => $images]);
    exit;
}


// Handle final vote and image generation
if (isset($_POST['finalVote'])) {
    $suggestions = file_exists($suggestions_file) ? file($suggestions_file, FILE_IGNORE_NEW_LINES) : [];
    $all_suggestions = file_exists($all_suggestions_file) ? file($all_suggestions_file, FILE_IGNORE_NEW_LINES) : [];

    // Scrape images for each suggestion and generate image and word collages
    $scraped_images = scrapeImagesForKeywords($suggestions, $image_directory);
    $image_collage_path = generateImageCollage($scraped_images, $image_directory);
    $word_collage_path = generateWordCollage($suggestions, $image_directory);

    // Update master grid with new suggestions
    $master_image_path = generateMegaImageFromAllSuggestions($all_suggestions, $image_directory);

    // Clear suggestions for the next round
    file_put_contents($suggestions_file, "");

    echo json_encode([
        'status' => 'success',
        'image_collage' => $image_collage_path,
        'word_collage' => $word_collage_path,
        'master_image' => $master_image_path,
        'message' => 'Collages created successfully'
    ]);
    exit;
}

// Function to generate the mega image using all suggestions
function generateMegaImageFromAllSuggestions($all_suggestions, $directory) {
    $columns = 5;
    $width = 200;
    $height = 200;
    $spacing = 10;
    $rows = ceil(count($all_suggestions) / $columns);

    $canvas_width = $columns * ($width + $spacing) + $spacing;
    $canvas_height = $rows * ($height + $spacing) + $spacing;
    $canvas = imagecreatetruecolor($canvas_width, $canvas_height);

    $background_color = imagecolorallocate($canvas, 255, 255, 255);
    imagefill($canvas, 0, 0, $background_color);

    $x = $spacing;
    $y = $spacing;

    // Loop through each suggestion and add it to the canvas
    foreach ($all_suggestions as $index => $keyword) {
        $definition = getDefinitionFromDatabase($keyword);
        if ($definition) {
            $relatedWords = analyzeTextAndGetRelatedWords($keyword, $definition);
            $swirl_image = createSwirlPattern($keyword, $relatedWords, $width, $height, $directory);
            
            $keyword_image = imagecreatefrompng($swirl_image);
            imagecopy($canvas, $keyword_image, $x, $y, 0, 0, $width, $height);
            imagedestroy($keyword_image);
        }

        $x += $width + $spacing;
        if (($index + 1) % $columns == 0) {
            $x = $spacing;
            $y += $height + $spacing;
        }
    }

    $mega_image_filename = "$directory/mega_image_" . uniqid() . ".png";
    imagepng($canvas, $mega_image_filename);
    imagedestroy($canvas);

    return $mega_image_filename;
}
// Function to create a swirl pattern image
function createSwirlPattern($keyword, $relatedWords, $width, $height, $directory) {
    $image = imagecreatetruecolor($width, $height);
    $background_color = imagecolorallocate($image, 255, 255, 255);
    $text_color = imagecolorallocate($image, 0, 0, 0);
    imagefill($image, 0, 0, $background_color);

    // Draw the main keyword in the center
    $font_size = 5;
    $center_x = ($width / 2) - (imagefontwidth($font_size) * strlen($keyword) / 2);
    $center_y = ($height / 2) - (imagefontheight($font_size) / 2);
    imagestring($image, $font_size, $center_x, $center_y, $keyword, $text_color);

    // Draw related words around in a swirl pattern
    $radius = 40;
    $angle_step = 30;
    foreach ($relatedWords as $i => $related) {
        $angle = $i * $angle_step;
        $opacity = 127 - intval($related['opacity'] * 127);
        $related_color = imagecolorallocatealpha($image, 0, 0, 0, $opacity);
        $related_x = $center_x + $radius * cos(deg2rad($angle)) - (imagefontwidth($font_size) * strlen($related['word']) / 2);
        $related_y = $center_y + $radius * sin(deg2rad($angle)) - (imagefontheight($font_size) / 2);
        imagestring($image, $font_size, $related_x, $related_y, $related['word'], $related_color);

        $radius += 10; // Increase radius for each subsequent word
    }

    $filename = "$directory/" . md5($keyword) . "_swirl.png";
    imagepng($image, $filename);
    imagedestroy($image);

    return $filename;
}


// Function to retrieve the definition of a keyword from the MySQL database
function getDefinitionFromDatabase($keyword) {
    global $database_host, $database_user, $database_password, $database_name;

    // Connect to the database
    $conn = new mysqli($database_host, $database_user, $database_password, $database_name);

    // Check connection
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        return false;
    }

    // Prepare and execute the query
    $stmt = $conn->prepare("SELECT definition FROM word WHERE word = ?");
    $stmt->bind_param("s", $keyword);
    $stmt->execute();
    $stmt->bind_result($definition);
    $stmt->fetch();
    $stmt->close();
    $conn->close();

    return $definition ? $definition : false;
}

// Function to analyze text and get related words based on definitions and calculate similarity scores
function analyzeTextAndGetRelatedWords($keyword, $definition) {
    global $word_vectors;

    $relatedWords = [];
    $words = explode(" ", $definition);

    foreach ($words as $word) {
        $word = trim($word);
        if (!empty($word)) {
            // Check if both the keyword and the word have pre-trained vectors
            if (isset($word_vectors[$keyword]) && isset($word_vectors[$word])) {
                // Calculate cosine similarity between the keyword and the word
                $similarity = calculateCosineSimilarity($word_vectors[$keyword], $word_vectors[$word]);
                $opacity = $similarity; // Directly use similarity as opacity

                $relatedWords[] = [
                    'word' => $word,
                    'opacity' => min(max($opacity, 0.1), 1.0) // Ensure opacity is between 0.1 and 1.0
                ];
            } else {
                error_log("Word vector not found for '$word' or '$keyword'.");
            }
        }
    }

    return $relatedWords;
}

function calculateCosineSimilarity($vectorA, $vectorB) {
    $dotProduct = 0.0;
    $magnitudeA = 0.0;
    $magnitudeB = 0.0;

    foreach ($vectorA as $index => $valueA) {
        $valueB = $vectorB[$index] ?? 0; // Handle missing elements gracefully
        $dotProduct += $valueA * $valueB;
        $magnitudeA += pow($valueA, 2);
        $magnitudeB += pow($valueB, 2);
    }

    $magnitudeA = sqrt($magnitudeA);
    $magnitudeB = sqrt($magnitudeB);

    if ($magnitudeA == 0 || $magnitudeB == 0) {
        return 0.0; // Avoid division by zero and return zero similarity
    }

    return $dotProduct / ($magnitudeA * $magnitudeB);
}


// Function to load pre-trained word vectors from a file
function loadWordVectors($file) {
    if (!file_exists($file)) {
        error_log("Word vector file not found: $file");
        return [];
    }

    $wordVectors = json_decode(file_get_contents($file), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error decoding JSON from $file: " . json_last_error_msg());
        return [];
    }

    return $wordVectors ?: [];
}


// Function to scrape images for each keyword from a site like Pixabay
function scrapeImagesForKeywords($keywords, $directory) {
    $downloaded_images = [];

    foreach ($keywords as $keyword) {
        $image_url = getImageFromPixabay($keyword);
        if ($image_url) {
            // Download and save the image
            $image_data = file_get_contents($image_url);
            $image_filename = "$directory/" . md5($keyword) . ".jpg";
            file_put_contents($image_filename, $image_data);

            $downloaded_images[] = $image_filename;
        }
    }
    return $downloaded_images;
}

// Function to get the first image URL from Pixabay using scraping
function getImageFromPixabay($keyword) {
    $query = urlencode($keyword);
    $url = "https://pixabay.com/images/search/$query/";

    // Initialize cURL session
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

    // Get the HTML content
    $html = curl_exec($ch);
    curl_close($ch);

    if ($html === false) {
        return false;
    }

    // Use DOMDocument to parse HTML and find the first image URL
    $doc = new DOMDocument();
    libxml_use_internal_errors(true); // Disable warnings due to malformed HTML
    $doc->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    // Find the first image URL
    $image_nodes = $xpath->query('//img[contains(@class, "preview") or contains(@class, "search__result-image")]');

    if ($image_nodes->length > 0) {
        $image_url = $image_nodes->item(0)->getAttribute('src');
        // Return the absolute URL if it starts with https://
        if (strpos($image_url, 'https://') === 0) {
            return $image_url;
        }
    }

    return false;
}


// Function to create a collage of images for each suggestion
function generateImageCollage($images, $directory) {
    $columns = 3;
    $rows = ceil(count($images) / $columns);
    $width = 200;
    $height = 200;
    $spacing = 10;

    $collage_width = $columns * ($width + $spacing) + $spacing;
    $collage_height = $rows * ($height + $spacing) + $spacing;
    $collage = imagecreatetruecolor($collage_width, $collage_height);

    $background_color = imagecolorallocate($collage, 255, 255, 255);
    imagefill($collage, 0, 0, $background_color);

    $x = $spacing;
    $y = $spacing;
    foreach ($images as $index => $image_file) {
        $image = imagecreatefromjpeg($image_file);
        imagecopy($collage, $image, $x, $y, 0, 0, $width, $height);
        imagedestroy($image);

        $x += $width + $spacing;
        if (($index + 1) % $columns == 0) {
            $x = $spacing;
            $y += $height + $spacing;
        }
    }

    $collage_filename = "$directory/collage_image_" . uniqid() . ".png";
    imagepng($collage, $collage_filename);
    imagedestroy($collage);
    return $collage_filename;
}

// Function to create a text-based collage
function generateWordCollage($suggestions, $directory) {
    $columns = 3;
    $rows = ceil(count($suggestions) / $columns);
    $width = 200;
    $height = 200;
    $spacing = 10;

    $collage_width = $columns * ($width + $spacing) + $spacing;
    $collage_height = $rows * ($height + $spacing) + $spacing;
    $collage = imagecreatetruecolor($collage_width, $collage_height);

    $background_color = imagecolorallocate($collage, 255, 255, 255);
    $text_color = imagecolorallocate($collage, 0, 0, 0);
    imagefill($collage, 0, 0, $background_color);

    $x = $spacing;
    $y = $spacing;
    foreach ($suggestions as $index => $keyword) {
        $image = createTextPlaceholder($keyword, $width, $height, $text_color, $background_color);

        $image_filename = "$directory/" . md5($keyword) . "_text.png";
        imagepng($image, $image_filename);
        imagecopy($collage, $image, $x, $y, 0, 0, $width, $height);
        imagedestroy($image);

        $x += $width + $spacing;
        if (($index + 1) % $columns == 0) {
            $x = $spacing;
            $y += $height + $spacing;
        }
    }

    $collage_filename = "$directory/collage_word_" . uniqid() . ".png";
    imagepng($collage, $collage_filename);
    imagedestroy($collage);
    return $collage_filename;
}

// Function to update the master image with all images and keywords ever inputted
function updateMasterImage($all_suggestions, $directory) {
    $columns = 5;
    $width = 200;
    $height = 200;
    $spacing = 10;
    $rows = ceil(count($all_suggestions) / $columns);

    $collage_width = $columns * ($width + $spacing) + $spacing;
    $collage_height = $rows * ($height + $spacing) + $spacing;
    $collage = imagecreatetruecolor($collage_width, $collage_height);

    $background_color = imagecolorallocate($collage, 255, 255, 255);
    imagefill($collage, 0, 0, $background_color);

    $x = $spacing;
    $y = $spacing;
    foreach ($all_suggestions as $index => $keyword) {
        $image = createPlaceholderImage($keyword, $width, $height);

        imagecopy($collage, $image, $x, $y, 0, 0, $width, $height);
        imagedestroy($image);

        $x += $width + $spacing;
        if (($index + 1) % $columns == 0) {
            $x = $spacing;
            $y += $height + $spacing;
        }
    }

    $master_filename = "$directory/master_image.png";
    imagepng($collage, $master_filename);
    imagedestroy($collage);
    return $master_filename;
}

// Function to create a placeholder image with text
function createPlaceholderImage($keyword, $width, $height) {
    $image = imagecreatetruecolor($width, $height);
    $background_color = imagecolorallocate($image, 200, 200, 200); // Gray background
    $text_color = imagecolorallocate($image, 0, 0, 0); // Black text
    imagefill($image, 0, 0, $background_color);

    // Add the keyword text to the image
    $font_size = 5; // Built-in GD font size
    $text_x = ($width / 2) - (imagefontwidth($font_size) * strlen($keyword) / 2);
    $text_y = ($height / 2) - (imagefontheight($font_size) / 2);
    imagestring($image, $font_size, $text_x, $text_y, $keyword, $text_color);

    return $image;
}
?>
