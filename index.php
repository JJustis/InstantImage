<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Command Center - Word Visualization</title>
    <!-- Include Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #2b2b2b;
            color: #e0e0e0;
            font-family: 'Courier New', Courier, monospace;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .command-center {
            width: 80%;
            max-width: 1200px;
            border: 2px solid #444;
            background-color: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }

        h1 {
            text-align: center;
        }

        .input-section, .suggestions-section, .related-words, .image-gallery {
            margin: 20px 0;
        }

        .input-section {
            text-align: center;
        }

        .related-word {
            margin: 5px;
            padding: 5px 10px;
            border: 1px solid #666;
            border-radius: 4px;
            background-color: #333;
            display: inline-block;
        }

        /* Gallery styling */
        .image-gallery {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            padding-top: 20px;
        }

        .image-gallery .card {
            background-color: #333;
            margin: 10px;
            border: none;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
            transition: transform 0.2s;
        }

        .image-gallery .card:hover {
            transform: scale(1.05);
        }

        .image-gallery .card img {
            border-radius: 4px;
        }

        .image-gallery .card-title {
            color: #e0e0e0;
        }

        .related-words {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }

        .related-word {
            margin: 5px;
            padding: 5px 10px;
            border: 1px solid #666;
            border-radius: 4px;
            background-color: #333;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container command-center">
        <h1>Command Center - Word Visualization</h1>
        
        <!-- Input Section -->
        <div class="input-section">
            <div class="input-group mb-3">
                <input type="text" id="userInput" name="userInput" class="form-control" placeholder="Enter your suggestion" required>
                <div class="input-group-append">
                    <button class="btn btn-primary" onclick="submitSuggestion()">Submit Suggestion</button>
                </div>
            </div>
        </div>

        <!-- Live Suggestions Section -->
        <div class="suggestions-section">
            <h2>Live Suggestions:</h2>
            <ul id="suggestions-list" class="list-group"></ul>
        </div>

        <!-- Related Words Section -->
        <div class="related-words" id="related-words-section">
            <!-- Related words and opacity will be displayed here -->
        </div>

        <!-- Image Gallery Section -->
        <div class="image-section">
            <h2>Generated Images</h2>
            <div id="image-gallery" class="row image-gallery">
                <!-- Images will be dynamically loaded here as cards -->
            </div>
        </div>
    </div>

    <!-- Include Bootstrap and jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        let suggestions = [];

        // Function to submit a suggestion
        function submitSuggestion() {
            const suggestion = document.getElementById("userInput").value;
            if (suggestion.trim() !== "") {
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "process.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.status === 'success') {
                                document.getElementById("userInput").value = ""; // Clear input field
                                loadSuggestions();
                            } else {
                                console.error("Error:", response.message);
                            }
                        } catch (e) {
                            console.error("Failed to parse JSON:", xhr.responseText);
                        }
                    }
                };
                xhr.send("userInput=" + encodeURIComponent(suggestion));
            }
        }

        // Function to load and update suggestions list
        function loadSuggestions() {
            const xhr = new XMLHttpRequest();
            xhr.open("GET", "process.php?getSuggestions=true", true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.suggestions) {
                            updateSuggestions(response.suggestions);
                            updateImages(response.suggestions);
                            updateRelatedWords(response.relatedWords);
                        } else {
                            console.error("Invalid JSON structure:", response);
                        }
                    } catch (e) {
                        console.error("Failed to parse JSON:", xhr.responseText);
                    }
                }
            };
            xhr.send();
        }

        // Update suggestions list in the UI
        function updateSuggestions(newSuggestions) {
            const suggestionsList = document.getElementById("suggestions-list");
            suggestionsList.innerHTML = ""; // Clear previous suggestions
            newSuggestions.forEach(suggestion => {
                const listItem = document.createElement("li");
                listItem.className = "list-group-item";
                listItem.innerText = suggestion;
                suggestionsList.appendChild(listItem);
            });
        }

        // Update images in the UI
        function updateImages(suggestions) {
            const imageGallery = document.getElementById("image-gallery");
            imageGallery.innerHTML = ""; // Clear previous images
            suggestions.forEach(suggestion => {
                const card = document.createElement("div");
                card.className = "col-md-3 col-sm-6";
                
                const imageCard = `
                    <div class="card">
                        <img src="tmp_images/${encodeURIComponent(suggestion)}.png" class="card-img-top" alt="${suggestion}">
                        <div class="card-body">
                            <h5 class="card-title">${suggestion}</h5>
                        </div>
                    </div>`;
                card.innerHTML = imageCard;
                imageGallery.appendChild(card);
            });
        }

        // Update related words around the keyword
        function updateRelatedWords(relatedWords) {
            const relatedSection = document.getElementById("related-words-section");
            relatedSection.innerHTML = ""; // Clear previous related words
            relatedWords.forEach(wordObj => {
                const wordDiv = document.createElement("div");
                wordDiv.classList.add("related-word");
                wordDiv.style.opacity = wordObj.opacity; // Use opacity based on similarity
                wordDiv.innerText = wordObj.word;
                relatedSection.appendChild(wordDiv);
            });
        }

        // Load suggestions on page load
        loadSuggestions();
    </script>
</body>
</html>
