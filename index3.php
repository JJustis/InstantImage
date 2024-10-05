<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Cropping and Compositing with Similarity Scores</title>
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
            margin: 10px;
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
    <button id="saveCropped" onclick="saveCroppedImage()">Save Cropped Image</button> <!-- New Save Cropped Image Button -->
    <div class="canvas-container">
        <canvas id="originalCanvas" width="500" height="500"></canvas>
        <canvas id="processedCanvas" width="500" height="500"></canvas>
    </div>
    <div class="canvas-container">
        <canvas id="transparentCanvas" width="500" height="500"></canvas>
    </div>
    <div class="canvas-container">
        <canvas id="compositeCanvas" width="800" height="600"></canvas>
    </div>

    <script>
        let originalImage, processedImage;
        const transparentCanvas = document.getElementById('transparentCanvas');
        const transparentCtx = transparentCanvas.getContext('2d');

        const originalCanvas = document.getElementById("originalCanvas");
        const processedCanvas = document.getElementById("processedCanvas");
        const compositeCanvas = document.getElementById("compositeCanvas");
        const compositeCtx = compositeCanvas.getContext('2d');

        document.getElementById("upload").addEventListener("change", handleImageUpload);
        document.getElementById("composite").addEventListener("click", compositeImage);
        document.getElementById("saveTransparent").addEventListener("click", saveTransparentImage);
        document.getElementById("generateComposite").addEventListener("click", generateCompositeGrid);

        // Load the uploaded image onto the canvas and preview it
        function handleImageUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                const img = new Image();
                img.onload = function () {
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

            const dataURL = transparentCanvas.toDataURL("image/png");

            // Create a download link and click it programmatically to save the image locally
            const link = document.createElement("a");
            link.href = dataURL;
            link.download = "transparent_image.png";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Save the cropped image to the server
        function saveCroppedImage() {
            if (!processedImage) {
                alert("Please composite an image first.");
                return;
            }

            const uniqueID = Date.now(); // Unique ID based on timestamp
            const similarityScore = Math.random().toFixed(6); // Random similarity score for demonstration

            const dataURL = transparentCanvas.toDataURL("image/png");
            const filename = `img_${uniqueID}_similarity_${similarityScore}.png`;

            // Save the image to the server
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "save_image.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function () {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            console.log("Image saved successfully!", response.path);
                            generateCompositeGrid(); // Refresh the composite grid after saving
                        } else {
                            console.error("Error saving image:", response.message);
                        }
                    } catch (e) {
                        console.error("Failed to parse JSON response:", xhr.responseText);
                    }
                } else {
                    console.error("Failed to save the image on the server.");
                }
            };
            xhr.send(`data=${encodeURIComponent(dataURL)}&filename=${encodeURIComponent(filename)}`);
        }

// Function to generate a composite grid of images based on similarity scores
function generateCompositeGrid() {
    compositeCtx.clearRect(0, 0, compositeCanvas.width, compositeCanvas.height);

    // Fetch image data from the server
    const xhr = new XMLHttpRequest();
    xhr.open("GET", "fetch_images_with_similarity.php", true); // Adjust this to match your PHP script
    xhr.onload = function () {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.status === 'success' && response.images.length > 0) {
                    const images = response.images;

                    // Sort images based on similarity score extracted from filename
                    images.sort((a, b) => {
                        const scoreA = parseFloat(a.filename.match(/similarity_([\d.]+)\.png$/)[1]);
                        const scoreB = parseFloat(b.filename.match(/similarity_([\d.]+)\.png$/)[1]);
                        return scoreB - scoreA; // Descending order (highest similarity first)
                    });

                    // Calculate grid size and dimensions based on number of images
                    const gridSize = Math.ceil(Math.sqrt(images.length)); // Calculate grid size
                    const cellWidth = compositeCanvas.width / gridSize;
                    const cellHeight = compositeCanvas.height / gridSize;

                    // Create new Image objects for each image and draw them on the composite canvas
                    images.forEach((imageData, index) => {
                        const img = new Image();
                        img.src = `tmp_images/${imageData.filename}`; // Use the image filename from the server response

                        img.onload = function () {
                            const row = Math.floor(index / gridSize);
                            const col = index % gridSize;
                            const x = col * cellWidth;
                            const y = row * cellHeight;
                            compositeCtx.drawImage(img, x, y, cellWidth, cellHeight);

                            // Optional: Draw similarity score on top of the image for visualization
                            compositeCtx.font = '12px Arial';
                            compositeCtx.fillStyle = 'white';
                            compositeCtx.fillText(`Score: ${parseFloat(imageData.similarityScore).toFixed(4)}`, x + 5, y + 15);
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

    </script>
</html>
