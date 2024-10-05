<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Cropping and Compositing with Transparent Background</title>
    <!-- Include OpenCV.js CDN -->
    <script async src="https://docs.opencv.org/4.x/opencv.js" type="text/javascript"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #f4f4f4;
            color: #333;
        }

        .canvas-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        canvas {
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        #upload, #composite, #saveTransparent, #generateComposite {
            margin: 20px;
        }
    </style>
</head>
<body>
    <h1>Image Cropping and Compositing with Transparent Background</h1>
    <input type="file" id="upload" accept="image/*" />
    <button id="composite">Composite Image</button>
    <button id="saveTransparent">Save Transparent Image</button>
    <button id="generateComposite">Generate Composite Grid</button>
    <div class="canvas-container">
        <canvas id="originalCanvas" width="500" height="500"></canvas>
        <canvas id="processedCanvas" width="500" height="500"></canvas>
    </div>
    <div class="canvas-container">
        <canvas id="compositeCanvas" width="600" height="600"></canvas>
    </div>
    <script>
        let originalImage, processedImage;
        const transparentCanvas = document.createElement('canvas');
        transparentCanvas.width = 500;
        transparentCanvas.height = 500;
        const transparentCtx = transparentCanvas.getContext('2d');

        const compositeCanvas = document.getElementById("compositeCanvas");
        const compositeCtx = compositeCanvas.getContext('2d');

        document.getElementById("upload").addEventListener("change", handleImageUpload);
        document.getElementById("composite").addEventListener("click", compositeImage);
        document.getElementById("saveTransparent").addEventListener("click", saveTransparentImage);
        document.getElementById("generateComposite").addEventListener("click", generateCompositeGrid);

        // Load the uploaded image onto the canvas
        function handleImageUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                const img = new Image();
                img.onload = function () {
                    const originalCanvas = document.getElementById("originalCanvas");
                    const ctx = originalCanvas.getContext("2d");
                    ctx.clearRect(0, 0, originalCanvas.width, originalCanvas.height);
                    ctx.drawImage(img, 0, 0, originalCanvas.width, originalCanvas.height);
                    originalImage = cv.imread(originalCanvas);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        // Function to apply edge detection and outline the subject
        function detectAndOutlineSubject() {
            const originalCanvas = document.getElementById("originalCanvas");
            const processedCanvas = document.getElementById("processedCanvas");

            // Check if the original image is loaded
            if (!originalImage) {
                alert("Please upload an image first.");
                return;
            }

            // Convert the image to grayscale and apply Canny edge detection
            const gray = new cv.Mat();
            cv.cvtColor(originalImage, gray, cv.COLOR_RGBA2GRAY, 0);
            const edges = new cv.Mat();
            cv.Canny(gray, edges, 50, 100, 3, false);

            // Find contours and draw them on the processed canvas
            const contours = new cv.MatVector();
            const hierarchy = new cv.Mat();
            cv.findContours(edges, contours, hierarchy, cv.RETR_EXTERNAL, cv.CHAIN_APPROX_SIMPLE);

            // Create a mask with the contours and apply it to the original image
            const mask = new cv.Mat.zeros(originalImage.rows, originalImage.cols, cv.CV_8UC1);
            for (let i = 0; i < contours.size(); ++i) {
                const color = new cv.Scalar(255, 255, 255);
                cv.drawContours(mask, contours, i, color, -1, 8, hierarchy, 0);
            }

            // Use the mask to extract the subject
            processedImage = new cv.Mat();
            originalImage.copyTo(processedImage, mask);

            // Draw the result on the canvas
            cv.imshow(processedCanvas, processedImage);

            // Draw the result on the transparent canvas
            transparentCtx.clearRect(0, 0, transparentCanvas.width, transparentCanvas.height);
            const imageData = processedImage.data;
            const img = new ImageData(new Uint8ClampedArray(imageData), processedImage.cols, processedImage.rows);
            transparentCtx.putImageData(img, 0, 0);

            // Clean up
            gray.delete();
            edges.delete();
            contours.delete();
            hierarchy.delete();
            mask.delete();
        }

        // Composite the cropped subject into a new canvas with additional transformations
        function compositeImage() {
            detectAndOutlineSubject();  // Perform subject detection and cropping
        }

        // Save the processed transparent image
        function saveTransparentImage() {
            // Check if processed image is available
            if (!processedImage) {
                alert("Please composite an image first.");
                return;
            }

            // Create a unique ID and similarity score for the filename
            const uniqueID = Date.now(); // Using current timestamp as unique ID
            const similarityScore = Math.random().toFixed(2); // Placeholder for similarity score calculation

            // Save the transparent canvas as a PNG with unique naming
            const dataURL = transparentCanvas.toDataURL("image/png");
            const filename = `img_${uniqueID}_similarity_${similarityScore}.png`;

            // Save the image to the server (replace with backend implementation)
            uploadImageToServer(dataURL, filename);

            // Display the updated composite grid
            generateCompositeGrid();
        }

        // Upload image to the server
        function uploadImageToServer(dataURL, filename) {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "save_image.php", true); // Replace with actual server-side script
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function () {
                if (xhr.status === 200) {
                    console.log("Image saved successfully!");
                } else {
                    console.error("Failed to save the image.");
                }
            };
            xhr.send(`data=${encodeURIComponent(dataURL)}&filename=${encodeURIComponent(filename)}`);
        }
document.getElementById("upload").addEventListener("change", handleFileUpload);

function handleFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Create FormData to send the file
    const formData = new FormData();
    formData.append('targetImage', file);

    // Send the image to the PHP script for similarity calculation
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "calculate_similarity.php", true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    console.log('Similarity Scores:', response.similarityScores);
                    console.log('Generated Image Path:', response.imagePath);

                    // Display the similarity map image
                    const img = new Image();
                    img.src = response.imagePath;
                    document.body.appendChild(img);
                } else {
                    console.error('Error:', response.message);
                }
            } catch (e) {
                console.error("Failed to parse JSON response:", xhr.responseText);
            }
        } else {
            console.error('Failed to process the image.');
        }
    };
    xhr.send(formData);
}

        // Generate a composite grid of all saved transparent images
        function generateCompositeGrid() {
            // Clear the existing composite canvas
            compositeCtx.clearRect(0, 0, compositeCanvas.width, compositeCanvas.height);

            // Fetch image data from the server
            const xhr = new XMLHttpRequest();
            xhr.open("GET", "fetch_images.php", true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.status === 'success' && response.images.length > 0) {
                            const images = response.images;
                            const gridSize = Math.ceil(Math.sqrt(images.length)); // Calculate grid size
                            const cellSize = compositeCanvas.width / gridSize;

                            // Create new Image objects for each image and draw them on the composite canvas
                            images.forEach((filename, index) => {
                                const img = new Image();
                                img.src = `tmp_images/${filename}`; // Use the image filename from the server response

                                img.onload = function () {
                                    const row = Math.floor(index / gridSize);
                                    const col = index % gridSize;
                                    const x = col * cellSize;
                                    const y = row * cellSize;
                                    compositeCtx.drawImage(img, x, y, cellSize, cellSize);
                                };
                            });
                        } else {
                            console.error("No images found or failed to fetch images.");
                        }
                    } catch (e) {
                        console.error("Failed to parse JSON response:", xhr.responseText);
                    }
                }
            };
            xhr.send();
        }

        // Wait for OpenCV.js to be ready before enabling buttons
        cv['onRuntimeInitialized'] = () => {
            console.log("OpenCV.js is ready.");
        };
		function loadCompositeImage() {
    const xhr = new XMLHttpRequest();
    xhr.open("GET", "compare_images.php", true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    const compositeImage = new Image();
                    compositeImage.src = response.compositeImage;
                    compositeImage.alt = "Similarity Composite Image";
                    document.getElementById("image-section").appendChild(compositeImage);
                } else {
                    console.error("Error:", response.message);
                }
            } catch (e) {
                console.error("Failed to parse JSON response:", xhr.responseText);
            }
        }
    };
    xhr.send();
}

    </script>
</body>
</html>
