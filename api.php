<?php
/**
 * Admin Games Content & Media API
 * Handles word editing, image uploads, deletion, and code saving
 */
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Ensure only authenticated admin can use the API
if (!is_admin_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in as admin.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$gamesBaseDir = realpath(__DIR__ . '/../games');
$photoDir = realpath(__DIR__ . '/../photo') ?: (__DIR__ . '/../photo');

if (!is_dir($photoDir)) {
    @mkdir($photoDir, 0777, true);
}

// 1. GET GAME WORDS (specifically for khmer_word_collection & other games)
if ($action === 'get_game_words') {
    $game = $_GET['game'] ?? 'khmer_word_collection';
    $filePath = $gamesBaseDir . '/' . basename($game) . '/index.html';

    if (!file_exists($filePath)) {
        echo json_encode(['success' => false, 'message' => 'Game file not found.']);
        exit;
    }

    $content = file_get_contents($filePath);

    // Parse wordsData for khmer_word_collection
    if ($game === 'khmer_word_collection') {
        if (preg_match('/const\s+wordsData\s*=\s*(\{[\s\S]*?\n\s*\};)/u', $content, $matches)) {
            $jsObj = $matches[1];
            // Remove trailing semicolon
            $jsonCandidate = rtrim($jsObj, ';');
            // Normalize keys to standard JSON: 1: -> "1":
            $jsonCandidate = preg_replace('/(\s*)(\d+)\s*:/', '$1"$2":', $jsonCandidate);
            // Remove trailing commas in arrays/objects
            $jsonCandidate = preg_replace('/,\s*([\]\}])/', '$1', $jsonCandidate);
            
            $data = json_decode($jsonCandidate, true);
            if ($data !== null) {
                echo json_encode(['success' => true, 'data' => $data, 'type' => 'wordsData']);
                exit;
            }
        }
    }

    // Fallback: extract any Javascript array like `const words = [...]` or `const items = [...]`
    if (preg_match('/const\s+(words|items|wordList|vocabulary)\s*=\s*(\[[\s\S]*?\n\s*\];)/u', $content, $matches)) {
        $varName = $matches[1];
        $jsArr = rtrim($matches[2], ';');
        $jsArr = preg_replace('/,\s*([\]\}])/', '$1', $jsArr);
        $jsArr = preg_replace('/(\s*)(\w+)\s*:/', '$1"$2":', $jsArr);
        $data = json_decode($jsArr, true);

        if ($data !== null) {
            echo json_encode(['success' => true, 'data' => $data, 'varName' => $varName, 'type' => 'array']);
            exit;
        }
    }

    echo json_encode(['success' => true, 'data' => null, 'raw_available' => true, 'message' => 'Custom structured format. Use Code Editor for direct modifications.']);
    exit;
}

// 2. SAVE GAME WORDS (for khmer_word_collection)
if ($action === 'save_game_words') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $game = $input['game'] ?? 'khmer_word_collection';
    $wordsData = $input['wordsData'] ?? null;

    if (!$wordsData || !is_array($wordsData)) {
        echo json_encode(['success' => false, 'message' => 'Invalid words data payload.']);
        exit;
    }

    $filePath = $gamesBaseDir . '/' . basename($game) . '/index.html';
    if (!file_exists($filePath)) {
        echo json_encode(['success' => false, 'message' => 'Game file not found.']);
        exit;
    }

    $content = file_get_contents($filePath);

    // Create backup file first
    @copy($filePath, $filePath . '.bak');

    if ($game === 'khmer_word_collection') {
        // Build pretty javascript object
        $jsFormatted = "const wordsData = {\n";
        $gradeCount = count($wordsData);
        $i = 0;
        foreach ($wordsData as $grade => $wordsList) {
            $i++;
            $jsFormatted .= "            " . intval($grade) . ": [\n";
            $wordLines = [];
            foreach ($wordsList as $w) {
                $word = trim($w);
                if ($word !== '') {
                    $wordLines[] = '                ' . json_encode($word, JSON_UNESCAPED_UNICODE);
                }
            }
            $jsFormatted .= implode(",\n", $wordLines) . "\n";
            $jsFormatted .= "            ]" . ($i < $gradeCount ? ',' : '') . "\n";
        }
        $jsFormatted .= "        };";

        // Replace in index.html
        $newContent = preg_replace('/const\s+wordsData\s*=\s*\{[\s\S]*?\n\s*\};/u', $jsFormatted, $content);
        if ($newContent !== null && file_put_contents($filePath, $newContent)) {
            echo json_encode(['success' => true, 'message' => 'ពាក្យទាំងអស់ត្រូវបានរក្សាទុកដោយជោគជ័យ! (Saved successfully)']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to write to file. Please check folder permissions.']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Unsupported automatic format for this game.']);
    exit;
}

// 3. GET IMAGES LIST (from /photo/ and game folders)
if ($action === 'list_images') {
    $images = [];
    $allowedExts = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];

    // Scan /photo/
    if (is_dir($photoDir)) {
        $files = scandir($photoDir);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExts)) {
                $fullPath = $photoDir . '/' . $f;
                $images[] = [
                    'filename' => $f,
                    'folder' => 'photo',
                    'url' => '../photo/' . rawurlencode($f),
                    'size' => filesize($fullPath),
                    'mtime' => filemtime($fullPath),
                    'path' => 'photo/' . $f
                ];
            }
        }
    }

    // Scan /assets/ and /icon/ if exist
    $extraDirs = ['assets' => __DIR__ . '/../assets', 'icon' => __DIR__ . '/../icon'];
    foreach ($extraDirs as $key => $dir) {
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExts)) {
                    $fullPath = $dir . '/' . $f;
                    $images[] = [
                        'filename' => $f,
                        'folder' => $key,
                        'url' => '../' . $key . '/' . rawurlencode($f),
                        'size' => filesize($fullPath),
                        'mtime' => filemtime($fullPath),
                        'path' => $key . '/' . $f
                    ];
                }
            }
        }
    }

    echo json_encode(['success' => true, 'images' => $images]);
    exit;
}

// 4. UPLOAD NEW IMAGE (បន្ថែមរូបភាព)
if ($action === 'upload_image') {
    if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error occurred.']);
        exit;
    }

    $file = $_FILES['image_file'];
    $allowedExts = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PNG, JPG, JPEG, GIF, SVG, WEBP.']);
        exit;
    }

    // Clean file name
    $targetDir = $photoDir;
    $customFolder = $_POST['target_folder'] ?? 'photo';
    if ($customFolder === 'icon') {
        $targetDir = __DIR__ . '/../icon';
    } elseif ($customFolder === 'assets') {
        $targetDir = __DIR__ . '/../assets';
    }

    $cleanName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $finalFilename = $cleanName . '_' . time() . '.' . $ext;
    $destination = $targetDir . '/' . $finalFilename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        echo json_encode([
            'success' => true,
            'message' => 'រូបភាពត្រូវបានបញ្ចូលជោគជ័យ! (Image uploaded successfully)',
            'file' => [
                'filename' => $finalFilename,
                'url' => '../' . $customFolder . '/' . $finalFilename,
                'path' => $customFolder . '/' . $finalFilename
            ]
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file. Check folder permissions.']);
        exit;
    }
}

// 5. DELETE IMAGE (លុបរូបភាព)
if ($action === 'delete_image') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $filePathRel = $input['path'] ?? '';

    // Prevent directory traversal
    $filePathRel = str_replace(['..', '\\'], ['', '/'], $filePathRel);
    $fullPath = realpath(__DIR__ . '/../' . $filePathRel);
    $rootPath = realpath(__DIR__ . '/..');

    if ($fullPath && strpos($fullPath, $rootPath) === 0 && file_exists($fullPath) && is_file($fullPath)) {
        if (@unlink($fullPath)) {
            echo json_encode(['success' => true, 'message' => 'រូបភាពត្រូវបានលុបជោគជ័យ! (Image deleted successfully)']);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not delete the file.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'File not found or invalid path.']);
        exit;
    }
}

// 6. GET RAW GAME SOURCE CODE (for Live Code Editor)
if ($action === 'get_game_code') {
    $game = $_GET['game'] ?? '';
    $filePath = $gamesBaseDir . '/' . basename($game) . '/index.html';

    if (file_exists($filePath)) {
        echo json_encode([
            'success' => true,
            'game' => $game,
            'code' => file_get_contents($filePath),
            'last_modified' => date('Y-m-d H:i:s', filemtime($filePath)),
            'size' => filesize($filePath)
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Game file not found.']);
        exit;
    }
}

// 7. SAVE RAW GAME SOURCE CODE (for Live Code Editor)
if ($action === 'save_game_code') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $game = $input['game'] ?? '';
    $code = $input['code'] ?? '';

    $filePath = $gamesBaseDir . '/' . basename($game) . '/index.html';
    if (!file_exists($filePath)) {
        echo json_encode(['success' => false, 'message' => 'Game file not found.']);
        exit;
    }

    // Make backup
    @copy($filePath, $filePath . '.bak');

    if (file_put_contents($filePath, $code) !== false) {
        echo json_encode([
            'success' => true,
            'message' => "ហ្គេម '{$game}' ត្រូវបានកែប្រែ និងរក្សាទុកដោយជោគជ័យ! (Game code saved successfully!)"
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save code. Check file permissions.']);
        exit;
    }
}

// 8. GET ALL GAMES (with metadata)
if ($action === 'get_all_games') {
    $games = [];
    if (is_dir($gamesBaseDir)) {
        $folders = scandir($gamesBaseDir);
        foreach ($folders as $f) {
            if ($f === '.' || $f === '..') continue;
            $fullDir = $gamesBaseDir . '/' . $f;
            if (is_dir($fullDir)) {
                $htmlFile = $fullDir . '/index.html';
                $phpFile = $fullDir . '/index.php';
                $mainFile = file_exists($htmlFile) ? $htmlFile : (file_exists($phpFile) ? $phpFile : null);
                
                $hasWords = in_array($f, ['khmer_word_collection', 'spelling_bee', 'antonym', 'synonym', 'word_scramble', 'flashcards', 'khmer_consonants', 'riddles_khmer', 'proverbs_quiz', 'picture_matching']);
                
                $games[] = [
                    'folder' => $f,
                    'name' => ucwords(str_replace(['_', '-'], ' ', $f)),
                    'has_main_file' => ($mainFile !== null),
                    'size' => $mainFile ? filesize($mainFile) : 0,
                    'last_modified' => $mainFile ? date('Y-m-d H:i:s', filemtime($mainFile)) : '-',
                    'has_words' => $hasWords,
                    'url' => '../games/' . $f . '/index.html'
                ];
            }
        }
    }
    echo json_encode(['success' => true, 'games' => $games, 'count' => count($games)]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);

