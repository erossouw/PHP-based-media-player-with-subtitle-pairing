<?php
// Directory containing the media files (.mp3, .mp4, etc.) and .srt files
$directory = __DIR__;

// Scan for media files and SRT files
$mediaFiles = glob("$directory/*.{mp3,mp4,ogg,webm}", GLOB_BRACE);
$subtitleFiles = glob("$directory/*.srt");

// Pair media files with their corresponding SRT files based on filenames
$mediaPairs = [];
foreach ($mediaFiles as $mediaFile) {
    $filename = pathinfo($mediaFile, PATHINFO_FILENAME);
    $subtitleFile = "$directory/$filename.srt";
    $enSubtitleFile = "$directory/$filename.en.srt"; // English subtitle file

    // Check if both subtitle files exist
    if (file_exists($subtitleFile)) {
        $mediaPairs[] = [
            'media' => basename($mediaFile),
            'subtitle' => $subtitleFile,
            'enSubtitle' => file_exists($enSubtitleFile) ? $enSubtitleFile : null, // Only include if exists
        ];
    }
}

// Helper function to convert "HH:MM:SS,MS" to seconds
function timeToSeconds($time) {
    sscanf($time, "%d:%d:%d,%d", $hours, $minutes, $seconds, $milliseconds);
    return $hours * 3600 + $minutes * 60 + $seconds + $milliseconds / 1000;
}

// Load subtitles from an .srt file and convert them to a usable format
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
    <meta charset="UTF-8">
    <title>Media Player with Subtitles</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #1e1e1e;
            color: #ffffff;
            overflow: hidden;
            display: flex;
        }
        .content {
            width: 65%;
            padding: 20px;
            overflow-y: auto;
            max-height: 100vh;
            border-right: 2px solid #444;
        }
        .media-container {
            margin-bottom: 40px;
        }
        .subtitle {
            font-size: 1.2em;
            margin-top: 10px;
            color: #ffffff;
            cursor: pointer;
            display: block;
        }
        .subtitle:hover {
            background-color: #444;
        }
        .video-box {
            position: absolute;
            right: 10px;
            width: 30vw;
            height: 30vh;
            z-index: 1000;
            background-color: black;
            resize: both;
            overflow: hidden;
            border: 2px solid #444;
            cursor: move;
        }
        .video-box video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="content">
        <h1>Media Player with Subtitles (Chrome & Opera users, click anywhere to start media playback. Firefox should auto-start playback on mouseover activity)</h1>

        <?php if (empty($mediaPairs)): ?>
            <p>No media-subtitle pairs found. Please ensure that media files and .srt files with matching names are present in the directory.</p>
        <?php else: ?>
            <?php foreach ($mediaPairs as $index => $pair): 
                $mediaFilePath = $pair['media'];
                $subtitles = loadSubtitles($pair['subtitle']);
                $enSubtitles = $pair['enSubtitle'] ? loadSubtitles($pair['enSubtitle']) : null; // Check if English subtitles exist
            ?>
                <div class="media-container">
                    <h2><?= htmlspecialchars(basename($mediaFilePath, pathinfo($mediaFilePath, PATHINFO_EXTENSION))) ?></h2>

                    <div class="video-box" id="video-box-<?= $index ?>" style="top: <?= $index * 35 ?>vh;">
                        <video id="media-<?= $index ?>" src="<?= htmlspecialchars($mediaFilePath) ?>" preload="auto" controls></video>
                    </div>

                    <div id="subtitles-<?= $index ?>" class="subtitle-display">
                        <?php foreach ($subtitles as $subtitleIndex => $subtitle): 
                            $enSubtitle = $enSubtitles[$subtitleIndex] ?? ['text' => 'No translation available']; // If no English subtitle, set default text
                        ?>
                            <span class="subtitle"
                                  data-start="<?= $subtitle['start'] ?>"
                                  data-end="<?= $subtitle['end'] ?>"
                                  title="<?= htmlspecialchars($enSubtitle['text']) ?>"
                                  onmouseover="playMediaAt(<?= $index ?>, <?= $subtitle['start'] ?>, <?= $subtitle['end'] ?>)"
                                  onmouseout="stopMedia(<?= $index ?>)">
                                <?= htmlspecialchars($subtitle['text']) ?>
                            </span><br>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        let mediaTimeout;

        function playMediaAt(index, start, end) {
            const media = document.getElementById(`media-${index}`);
            media.currentTime = start;
            media.play();

            clearTimeout(mediaTimeout);
            mediaTimeout = setTimeout(() => {
                media.pause();
            }, (end - start) * 1000);
        }

        function stopMedia(index) {
            const media = document.getElementById(`media-${index}`);
            media.pause();
            clearTimeout(mediaTimeout);
        }

        // Draggable and resizable video boxes
        document.querySelectorAll('.video-box').forEach((videoBox) => {
            let isDragging = false;
            let startX, startY, initialX, initialY;

            videoBox.addEventListener("mousedown", (e) => {
                isDragging = true;
                startX = e.clientX;
                startY = e.clientY;
                initialX = videoBox.offsetLeft;
                initialY = videoBox.offsetTop;
                videoBox.style.cursor = "grabbing";
            });

            document.addEventListener("mousemove", (e) => {
                if (isDragging) {
                    let newLeft = initialX + e.clientX - startX;
                    let newTop = initialY + e.clientY - startY;

                    newLeft = Math.min(Math.max(0, newLeft), window.innerWidth - videoBox.offsetWidth);
                    newTop = Math.min(Math.max(0, newTop), window.innerHeight - videoBox.offsetHeight);

                    videoBox.style.left = newLeft + "px";
                    videoBox.style.top = newTop + "px";
                }
            });

            document.addEventListener("mouseup", () => {
                isDragging = false;
                videoBox.style.cursor = "move";
            });
        });
    </script>
</body>
</html>
