<?php
// Directory containing the media files (.mp3, .mp4, etc.) and .srt files
$directory = __DIR__; // current directory, or specify another path

// Scan for media files and SRT files
$mediaFiles = glob("$directory/*.{mp3,mp4,ogg,webm}", GLOB_BRACE);
$subtitleFiles = glob("$directory/*.srt");

// Pair media files with their corresponding SRT files based on filenames
$mediaPairs = [];
foreach ($mediaFiles as $mediaFile) {
    $filename = pathinfo($mediaFile, PATHINFO_FILENAME);
    $subtitleFile = "$directory/$filename.srt";
    if (file_exists($subtitleFile)) {
        $mediaPairs[] = [
            'media' => basename($mediaFile),
            'subtitle' => basename($subtitleFile),
        ];
    }
}

// Helper function to convert "HH:MM:SS,MS" to seconds
function timeToSeconds($time) {
    sscanf($time, "%d:%d:%d,%d", $hours, $minutes, $seconds, $milliseconds);
    return $hours * 3600 + $minutes * 60 + $seconds + $milliseconds / 1000;
}

// Load subtitles from an .srt file
function loadSubtitles($subtitleFilePath) {
    $subtitles = [];
    $file = fopen($subtitleFilePath, 'r');
    if ($file) {
        while (($line = fgets($file)) !== false) {
            $line = trim($line);
            if (is_numeric($line)) {
                $timeLine = fgets($file);
                list($start, $end) = explode(' --> ', trim($timeLine));

                $startSeconds = timeToSeconds($start);
                $endSeconds = timeToSeconds($end);

                $text = '';
                while (($subtitleLine = fgets($file)) && trim($subtitleLine) !== '') {
                    $text .= trim($subtitleLine) . ' ';
                }
                $subtitles[] = [
                    'start' => $startSeconds,
                    'end' => $endSeconds,
                    'text' => trim($text),
                ];
            }
        }
        fclose($file);
    }
    return $subtitles;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="refresh" content="1000" charset="UTF-8">
	
    <title>Media Player with Subtitles</title>
    <style>
        :root {
            --background-color: #1e1e1e; /* Default background for night mode */
            --text-color: #ffffff; /* Default text color for night mode */
            --highlight-color-day: #f0f0f0; /* Light grey for day mode */
            --highlight-color-night: #444; /* Dark grey for night mode */
        }

        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: var(--background-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
        }

        .media-container {
            margin-bottom: 20px;
        }

        .subtitle {
            cursor: pointer;
            display: inline-block;
            margin: 5px 0;
            transition: background-color 0.3s;
        }

        .subtitle:hover {
            background-color: var(--highlight-color-day); /* Default hover color */
        }

        /* Night mode hover style */
        body.night-mode .subtitle:hover {
            background-color: var(--highlight-color-night); /* Darker hover color in night mode */
        }

        .controls {
            position: fixed;
            top: 10px;
            right: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .controls label {
            margin-right: 5px;
            font-size: 14px;
        }

        .day-night-toggle {
            cursor: pointer;
            padding: 5px 10px;
            background-color: #ddd;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .font-size-slider {
            width: 100px;
        }
    </style>
</head>
<body class="night-mode"> <!-- Added night mode class -->
    <div class="controls">
        <span class="day-night-toggle" onclick="toggleDayNight()">Day/Night</span>
        <label for="font-size-slider">Font Size:</label>
        <input type="range" id="font-size-slider" class="font-size-slider" min="12" max="240" value="16" oninput="adjustFontSize(this.value)">
    </div>

    <h1>Media Player with Subtitles</h1>

    <?php if (empty($mediaPairs)): ?>
        <p>No media-subtitle pairs found. Please ensure that media files and .srt files with matching names are present in the directory.</p>
    <?php else: ?>
        <?php foreach ($mediaPairs as $pair): ?>
            <?php
            $mediaFilePath = $pair['media'];
            $subtitleFilePath = $pair['subtitle'];
            $subtitles = loadSubtitles($subtitleFilePath);
            ?>

            <div class="media-container">
                <h2><?= htmlspecialchars(basename($mediaFilePath, pathinfo($mediaFilePath, PATHINFO_EXTENSION))) ?></h2>
                <video id="media-<?= htmlspecialchars(basename($mediaFilePath, pathinfo($mediaFilePath, PATHINFO_EXTENSION))) ?>" src="<?= htmlspecialchars($mediaFilePath) ?>" preload="auto" controls></video>
                <div id="subtitles-<?= htmlspecialchars(basename($mediaFilePath, pathinfo($mediaFilePath, PATHINFO_EXTENSION))) ?>">
                    <?php foreach ($subtitles as $subtitle): ?>
                        <span class="subtitle"
                              data-start="<?= $subtitle['start'] ?>"
                              data-end="<?= $subtitle['end'] ?>"
                              onmouseover="playMediaAt('<?= htmlspecialchars(basename($mediaFilePath, pathinfo($mediaFilePath, PATHINFO_EXTENSION))) ?>', <?= $subtitle['start'] ?>, <?= $subtitle['end'] ?>)"
                              onmouseout="stopMedia('<?= htmlspecialchars(basename($mediaFilePath, pathinfo($mediaFilePath, PATHINFO_EXTENSION))) ?>')">
                            <?= htmlspecialchars($subtitle['text']) ?>
                        </span><br>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <script>
        const timeouts = {};

        function playMediaAt(mediaId, start, end) {
            const media = document.getElementById(`media-${mediaId}`);
            clearTimeout(timeouts[mediaId]);
            media.currentTime = start;
            media.play();

            const duration = (end - start) * 1000;
            timeouts[mediaId] = setTimeout(() => {
                media.pause();
            }, duration);
        }

        function stopMedia(mediaId) {
            clearTimeout(timeouts[mediaId]);
            const media = document.getElementById(`media-${mediaId}`);
            media.pause();
        }

        function toggleDayNight() {
            document.body.classList.toggle('night-mode');
            const isNightMode = document.body.classList.contains('night-mode');
            document.body.style.setProperty('--background-color', isNightMode ? '#1e1e1e' : '#ffffff');
            document.body.style.setProperty('--text-color', isNightMode ? '#ffffff' : '#000000');
        }

        function adjustFontSize(size) {
            document.body.style.fontSize = size + 'px';
        }
    </script>
</body>
</html>
