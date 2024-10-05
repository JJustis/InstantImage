<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['data']) && isset($_POST['filename'])) {
        $data = $_POST['data'];
        $filename = $_POST['filename'];

        // Extract base64 data and decode it
        $data = str_replace('data:image/png;base64,', '', $data);
        $data = str_replace(' ', '+', $data);
        $decodedData = base64_decode($data);

        // Directory to save the images
        $directory = 'tmp_images';

        // Ensure the directory exists
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }

        // Save the image to the directory
        $filePath = $directory . '/' . $filename;
        if (file_put_contents($filePath, $decodedData)) {
            echo json_encode(['status' => 'success', 'message' => 'Image saved successfully', 'path' => $filePath]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save the image']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data or filename']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
