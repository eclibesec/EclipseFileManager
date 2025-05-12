<?php
session_start();
$current_filename = basename($_SERVER['SCRIPT_FILENAME']);

// Default credentials (username: admin, password: admin123)
$default_username = 'admin';
$default_password_hash = '$2a$12$UBIkydbkd50bmZuaQXb0NuvrPd7XeeIKbFaGRYdvJDIHkGQ8GxOZe'; // bcrypt hash of "admin123"

// Generate or retrieve chat session ID based on IP
if (!isset($_SESSION['chat_session_id'])) {
    $_SESSION['chat_session_id'] = md5($_SERVER['REMOTE_ADDR'] . time());
}

// Handle login
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === $default_username && password_verify($password, $default_password_hash)) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        
        $_SESSION['alert'] = [
            'type' => 'success',
            'message' => 'Login successful! Welcome to Eclipse File Manager.'
        ];
        
        header("Location: $current_filename");
        exit;
    } else {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'Invalid username or password.'
        ];
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: $current_filename");
    exit;
}

// Check authentication
$authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

// Path handling
$path = isset($_GET['path']) ? realpath($_GET['path']) : getcwd();
if (!$path || !is_dir($path)) $path = getcwd();

// File size formatting
function formatSize($s) {
    if ($s >= 1073741824) return round($s / 1073741824, 2) . ' GB';
    if ($s >= 1048576) return round($s / 1048576, 2) . ' MB';
    if ($s >= 1024) return round($s / 1024, 2) . ' KB';
    return $s . ' B';
}

// Search functionality
$search_results = [];
$search_query = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = $_GET['search'];
    $search_results = searchFiles($path, $search_query);
}

function searchFiles($directory, $query) {
    $results = [];
    $items = scandir($directory);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $fullPath = $directory . '/' . $item;
        
        // Check if name contains search query
        if (stripos($item, $query) !== false) {
            $results[] = [
                'path' => $fullPath,
                'name' => $item,
                'is_dir' => is_dir($fullPath),
                'size' => is_file($fullPath) ? filesize($fullPath) : 0
            ];
        }
        
        // Recursively search in subdirectories
        if (is_dir($fullPath)) {
            $subResults = searchFiles($fullPath, $query);
            $results = array_merge($results, $subResults);
        }
    }
    
    return $results;
}

// Only process actions if authenticated
if ($authenticated) {
    // Delete file/folder
    if (isset($_GET['delete'])) {
        $target = realpath($path . '/' . $_GET['delete']);
        if (strpos($target, $path) === 0 && is_writable($target)) {
            if (is_file($target)) {
                if (unlink($target)) {
                    $_SESSION['alert'] = [
                        'type' => 'success',
                        'message' => 'File deleted successfully.'
                    ];
                } else {
                    $_SESSION['alert'] = [
                        'type' => 'danger',
                        'message' => 'Failed to delete file.'
                    ];
                }
            } elseif (is_dir($target)) {
                if (rmdir($target)) {
                    $_SESSION['alert'] = [
                        'type' => 'success',
                        'message' => 'Folder deleted successfully.'
                    ];
                } else {
                    $_SESSION['alert'] = [
                        'type' => 'danger',
                        'message' => 'Failed to delete folder. Make sure it is empty.'
                    ];
                }
            }
        }
        header("Location: ?path=" . urlencode($path));
        exit;
    }

    // Rename file/folder
    if (isset($_POST['rename_from'], $_POST['rename_to'])) {
        $from = realpath($path . '/' . $_POST['rename_from']);
        $to = $path . '/' . basename($_POST['rename_to']);
        if (strpos($from, $path) === 0 && file_exists($from)) {
            if (rename($from, $to)) {
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'message' => 'Item renamed successfully.'
                ];
            } else {
                $_SESSION['alert'] = [
                    'type' => 'danger',
                    'message' => 'Failed to rename item.'
                ];
            }
        }
        header("Location: ?path=" . urlencode($path));
        exit;
    }

    // Create new folder
    if (isset($_POST['new_folder'])) {
        $folder_name = basename($_POST['new_folder']);
        if (mkdir($path . '/' . $folder_name)) {
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => "Folder '$folder_name' created successfully."
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => "Failed to create folder '$folder_name'."
            ];
        }
        header("Location: ?path=" . urlencode($path));
        exit;
    }

    // Create new file
    if (isset($_POST['new_file'])) {
        $file_name = basename($_POST['new_file']);
        if (file_put_contents($path . '/' . $file_name, '') !== false) {
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => "File '$file_name' created successfully."
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => "Failed to create file '$file_name'."
            ];
        }
        header("Location: ?path=" . urlencode($path));
        exit;
    }

    // Upload file(s)
    if (isset($_FILES['upload'])) {
        $upload_count = 0;
        $error_count = 0;
        
        // Check if it's a multi-file upload
        if (is_array($_FILES['upload']['name'])) {
            // Multi-file upload
            foreach ($_FILES['upload']['name'] as $key => $name) {
                if ($_FILES['upload']['error'][$key] === 0) {
                    $upload_name = basename($name);
                    if (move_uploaded_file($_FILES['upload']['tmp_name'][$key], $path . '/' . $upload_name)) {
                        $upload_count++;
                    } else {
                        $error_count++;
                    }
                } else {
                    $error_count++;
                }
            }
            
            if ($upload_count > 0) {
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'message' => "$upload_count file(s) uploaded successfully" . ($error_count > 0 ? ", $error_count failed" : ".")
                ];
            } else {
                $_SESSION['alert'] = [
                    'type' => 'danger',
                    'message' => "Failed to upload files."
                ];
            }
        } else {
            // Single file upload
            if ($_FILES['upload']['error'] === 0) {
                $upload_name = basename($_FILES['upload']['name']);
                if (move_uploaded_file($_FILES['upload']['tmp_name'], $path . '/' . $upload_name)) {
                    $_SESSION['alert'] = [
                        'type' => 'success',
                        'message' => "File '$upload_name' uploaded successfully."
                    ];
                } else {
                    $_SESSION['alert'] = [
                        'type' => 'danger',
                        'message' => "Failed to upload file '$upload_name'."
                    ];
                }
            } else {
                $_SESSION['alert'] = [
                    'type' => 'danger',
                    'message' => "Upload error: " . $_FILES['upload']['error']
                ];
            }
        }
        
        header("Location: ?path=" . urlencode($path));
        exit;
    }

    // Save file content
    if (isset($_POST['save_file'], $_POST['content'])) {
        $file = realpath($path . '/' . $_POST['save_file']);
        if (strpos($file, $path) === 0 && is_file($file)) {
            if (file_put_contents($file, $_POST['content']) !== false) {
                $_SESSION['alert'] = [
                    'type' => 'success',
                    'message' => "File saved successfully."
                ];
            } else {
                $_SESSION['alert'] = [
                    'type' => 'danger',
                    'message' => "Failed to save file."
                ];
            }
        }
        header("Location: ?path=" . urlencode($path) . "&edit=" . urlencode($_POST['save_file']));
        exit;
    }
}

// Get file extension for icon determination
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $iconMap = [
        'txt' => 'text',
        'pdf' => 'pdf',
        'doc' => 'word',
        'docx' => 'word',
        'xls' => 'excel',
        'xlsx' => 'excel',
        'ppt' => 'powerpoint',
        'pptx' => 'powerpoint',
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'gif' => 'image',
        'svg' => 'image',
        'mp3' => 'audio',
        'wav' => 'audio',
        'mp4' => 'video',
        'mov' => 'video',
        'zip' => 'archive',
        'rar' => 'archive',
        'tar' => 'archive',
        'gz' => 'archive',
        'php' => 'code',
        'html' => 'code',
        'css' => 'code',
        'js' => 'code',
        'json' => 'code',
        'xml' => 'code',
    ];
    
    return isset($iconMap[$ext]) ? $iconMap[$ext] : 'file';
}

// Get syntax highlighting mode for CodeMirror
function getEditorMode($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $modeMap = [
        'php' => 'application/x-httpd-php',
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'md' => 'text/x-markdown',
        'txt' => 'text/plain',
        'ini' => 'text/x-properties',
        'conf' => 'text/x-properties',
        'sql' => 'text/x-sql',
        'py' => 'text/x-python',
        'java' => 'text/x-java',
        'c' => 'text/x-csrc',
        'cpp' => 'text/x-c++src',
        'cs' => 'text/x-csharp',
        'go' => 'text/x-go',
        'rb' => 'text/x-ruby',
        'sh' => 'text/x-sh',
        'yaml' => 'text/x-yaml',
        'yml' => 'text/x-yaml',
    ];
    
    return isset($modeMap[$ext]) ? $modeMap[$ext] : 'text/plain';
}

// Check if file is viewable in browser
function isViewable($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $viewable = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'txt', 'md', 'html', 'htm'];
    return in_array($ext, $viewable);
}

// Get MIME type for file preview
function getMimeType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $mimeMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
        'html' => 'text/html',
        'htm' => 'text/html',
    ];
    
    return isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'application/octet-stream';
}

// Get theme preference
$theme = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eclipse File Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/dracula.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/eclipse.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/dialog/dialog.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/search/matchesonscrollbar.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/foldgutter.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/hint/show-hint.min.css">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --light: #f9fafb;
            --dark: #1e293b;
            --gray: #94a3b8;
            --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --radius: 0.375rem;
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        [data-theme="dark"] {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #94a3b8;
            --border: #334155;
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #cbd5e1;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2), 0 2px 4px -1px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            line-height: 1.5;
            transition: background-color 0.3s, color 0.3s;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--bg-main);
            padding: 1rem;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            background-color: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .login-header {
            padding: 1.5rem;
            background-color: var(--primary);
            color: white;
            text-align: center;
        }

        .login-body {
            padding: 1.5rem;
        }

        .login-footer {
            padding: 1rem 1.5rem;
            background-color: var(--bg-main);
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .sidebar {
            width: 250px;
            background-color: var(--bg-card);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            position: fixed;
            height: 100vh;
            z-index: 100;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar-content {
            padding: 1rem;
            flex: 1;
            overflow-y: auto;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid var(--border);
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            transition: all 0.3s ease;
        }

        .topbar {
            background-color: var(--bg-card);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
        }

        .breadcrumb-separator {
            margin: 0;
            color: var(--gray);
        }

        .breadcrumb-link {
            color: var(--secondary);
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb-link:hover {
            color: var(--primary);
        }

        .current-path {
            font-weight: 500;
            color: var(--text-main);
        }

        .content {
            padding: 1.5rem;
        }

        .file-list {
            background-color: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .file-list-header {
            display: grid;
            grid-template-columns: minmax(200px, 2fr) minmax(100px, 1fr) minmax(100px, 1fr) minmax(150px, 1fr);
            padding: 0.75rem 1rem;
            background-color: var(--bg-main);
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            color: var(--secondary);
        }

        .file-list-body {
            max-height: calc(100vh - 250px);
            overflow-y: auto;
        }

        .file-item {
            display: grid;
            grid-template-columns: minmax(200px, 2fr) minmax(100px, 1fr) minmax(100px, 1fr) minmax(150px, 1fr);
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            transition: background-color 0.2s;
            align-items: center;
        }

        .file-item:hover {
            background-color: var(--bg-main);
        }

        .file-name {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .file-icon {
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius);
            color: white;
            flex-shrink: 0;
        }

        .file-icon.folder {
            background-color: var(--warning);
        }

        .file-icon.file {
            background-color: var(--secondary);
        }

        .file-icon.image {
            background-color: var(--success);
        }

        .file-icon.code {
            background-color: var(--info);
        }

        .file-icon.pdf {
            background-color: var(--danger);
        }

        .file-icon.word {
            background-color: #2b579a;
        }

        .file-icon.excel {
            background-color: #217346;
        }

        .file-icon.powerpoint {
            background-color: #d24726;
        }

        .file-icon.archive {
            background-color: #a05a2c;
        }

        .file-icon.audio {
            background-color: #8e44ad;
        }

        .file-icon.video {
            background-color: #e74c3c;
        }

        .file-icon.text {
            background-color: var(--gray);
        }

        .file-link {
            color: var(--text-main);
            text-decoration: none;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-link:hover {
            color: var(--primary);
        }

        .file-size, .file-perm {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .file-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            outline: none;
            text-decoration: none;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .btn-icon {
            padding: 0.5rem;
            border-radius: 50%;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: var(--secondary);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #475569;
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #059669;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-light {
            background-color: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border);
        }

        .btn-light:hover {
            background-color: var(--bg-main);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border);
        }

        .btn-outline:hover {
            background-color: var(--bg-main);
        }

        .btn-block {
            width: 100%;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-main);
        }

        .form-control {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            font-size: 0.875rem;
            transition: border-color 0.2s;
            background-color: var(--bg-card);
            color: var(--text-main);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }

        textarea.form-control {
            min-height: 150px;
            font-family: 'Fira Code', 'Courier New', Courier, monospace;
        }

        .card {
            background-color: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-body {
            padding: 1.5rem;
        }

        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }

        .modal-backdrop.show {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background-color: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9);
            opacity: 0;
            transition: transform 0.3s, opacity 0.3s;
        }

        .modal-backdrop.show .modal {
            transform: scale(1);
            opacity: 1;
        }

        .modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-weight: 600;
            font-size: 1.125rem;
            color: var(--text-main);
        }

        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.25rem;
            color: var(--text-muted);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .alert-warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .alert-info {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-width: 350px;
        }

        .toast {
            background-color: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease-out forwards;
            position: relative;
            overflow: hidden;
        }

        .toast-success {
            border-left: 4px solid var(--success);
        }

        .toast-danger {
            border-left: 4px solid var(--danger);
        }

        .toast-warning {
            border-left: 4px solid var(--warning);
        }

        .toast-info {
            border-left: 4px solid var(--info);
        }

        .toast-icon {
            font-size: 1.25rem;
        }

        .toast-success .toast-icon {
            color: var(--success);
        }

        .toast-danger .toast-icon {
            color: var(--danger);
        }

        .toast-warning .toast-icon {
            color: var(--warning);
        }

        .toast-info .toast-icon {
            color: var(--info);
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-main);
        }

        .toast-message {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .toast-close {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: var(--text-muted);
            padding: 0.25rem;
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background-color: rgba(0, 0, 0, 0.1);
            width: 100%;
        }

        .toast-progress-bar {
            height: 100%;
            background-color: var(--primary);
            width: 100%;
            animation: progress 5s linear forwards;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        @keyframes progress {
            from {
                width: 100%;
            }
            to {
                width: 0%;
            }
        }

        .d-flex {
            display: flex;
        }

        .align-items-center {
            align-items: center;
        }

        .justify-content-between {
            justify-content: space-between;
        }

        .gap-2 {
            gap: 0.5rem;
        }

        .gap-3 {
            gap: 0.75rem;
        }

        .mb-3 {
            margin-bottom: 0.75rem;
        }

        .mb-4 {
            margin-bottom: 1rem;
        }

        .mt-4 {
            margin-top: 1rem;
        }

        .p-3 {
            padding: 0.75rem;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-sm {
            font-size: 0.875rem;
        }

        .text-xs {
            font-size: 0.75rem;
        }

        .text-muted {
            color: var(--text-muted);
        }

        .w-100 {
            width: 100%;
        }

        .flex-wrap {
            flex-wrap: wrap;
        }

        .editor-container {
    display: flex;
    flex-direction: column;
    height: 80vh !important;
    min-height: 500px !important;
    overflow: visible;
    position: relative;
    margin-bottom: 50px;
}

.editor-body {
    flex: 1;
    position: relative;
    height: 100%;
    min-height: 500px;
    overflow: visible;
}

.CodeMirror {
    position: absolute !important;
    top: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    left: 0 !important;
    height: auto !important;
    min-height: 500px !important;
    font-family: 'Fira Code', 'Courier New', Courier, monospace;
    font-size: 16px;
    line-height: 1.6;
}

        .editor-body {
            flex: 1;
            position: relative;
            height: 100%;
            overflow: hidden;
        }

        .editor-textarea {
            width: 100%;
            height: 100%;
            padding: 1rem;
            border: none;
            resize: none;
            font-family: 'Fira Code', 'Courier New', Courier, monospace;
            font-size: 0.875rem;
            line-height: 1.7;
            outline: none;
            background-color: var(--bg-card);
            color: var(--text-main);
        }

        .editor-footer {
            padding: 0.75rem 1rem;
            background-color: var(--bg-main);
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .CodeMirror {
    position: absolute !important;
    top: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    left: 0 !important;
    height: auto !important;
    min-height: 800px !important;
    font-family: 'Fira Code', 'Courier New', Courier, monospace;
    font-size: 16px;
    line-height: 1.6;
}

        .CodeMirror-scroll {
            height: 100%;
            overflow-y: auto !important;
            overflow-x: auto !important;
        }

        .cm-s-dracula.CodeMirror {
            background-color: #282a36;
        }

        .cm-s-eclipse.CodeMirror {
            background-color: var(--bg-card);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .mobile-toggle {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 240px;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .mobile-toggle {
                display: block;
            }
            
            .file-list-header {
                grid-template-columns: minmax(150px, 2fr) minmax(80px, 1fr) minmax(80px, 1fr);
            }
            
            .file-item {
                grid-template-columns: minmax(150px, 2fr) minmax(80px, 1fr) minmax(80px, 1fr);
            }
            
            .file-actions {
                grid-column: 1 / -1;
                justify-content: flex-start;
                margin-top: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .file-list-header {
                grid-template-columns: 1fr auto;
            }
            
            .file-item {
                grid-template-columns: 1fr auto;
            }
            
            .file-perm {
                display: none;
            }
            
            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .breadcrumb {
                width: 100%;
                overflow-x: auto;
                white-space: nowrap;
                padding-bottom: 0.5rem;
            }
        }

        .dropzone {
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s;
            background-color: var(--bg-main);
        }

        .dropzone:hover, .dropzone.dragover {
            border-color: var(--primary);
        }

        .dropzone-icon {
            font-size: 2rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .dropzone-text {
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .dropzone-hint {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--text-main);
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Sidebar Menu */
        .sidebar-menu {
            margin-bottom: 1.5rem;
        }

        .sidebar-menu-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            padding: 0 0.5rem;
        }

        .sidebar-menu-items {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu-item {
            margin-bottom: 0.25rem;
        }

        .sidebar-menu-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius);
            color: var(--text-main);
            text-decoration: none;
            transition: all 0.2s;
        }

        .sidebar-menu-link:hover {
            background-color: var(--bg-main);
            color: var(--primary);
        }

        .sidebar-menu-link.active {
            background-color: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            font-weight: 500;
        }

        .sidebar-menu-icon {
            font-size: 1rem;
            width: 1.25rem;
            text-align: center;
        }

        /* Search Box */
        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            padding-left: 2.5rem;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            font-size: 0.875rem;
            transition: border-color 0.2s;
            background-color: var(--bg-card);
            color: var(--text-main);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        /* Search Results */
        .search-results {
            background-color: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }

        .search-results-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            color: var(--text-main);
        }

        .search-results-body {
            max-height: 300px;
            overflow-y: auto;
        }

        .search-result-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            transition: background-color 0.2s;
        }

        .search-result-item:hover {
            background-color: var(--bg-main);
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-link {
            color: var(--text-main);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .search-result-link:hover {
            color: var(--primary);
        }

        .search-result-icon {
            width: 1.5rem;
            height: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius);
            color: white;
            flex-shrink: 0;
        }

        .search-result-name {
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .search-result-path {
            font-size: 0.75rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Theme Toggle */
        .theme-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius);
            color: var(--text-main);
            background-color: var(--bg-main);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s;
        }

        .theme-toggle:hover {
            background-color: var(--bg-main);
            color: var(--primary);
        }

        /* File Preview */
        .preview-container {
            background-color: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .preview-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .preview-title {
            font-weight: 600;
            font-size: 1.125rem;
            color: var(--text-main);
        }

        .preview-body {
            padding: 1.5rem;
            text-align: center;
        }

        .preview-image {
            max-width: 100%;
            max-height: 500px;
            border-radius: var(--radius);
        }

        .preview-pdf {
            width: 100%;
            height: 500px;
            border: none;
        }

        .preview-text {
            width: 100%;
            height: 500px;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-family: 'Fira Code', monospace;
            font-size: 0.875rem;
            line-height: 1.6;
            white-space: pre-wrap;
            overflow-y: auto;
            background-color: var(--bg-main);
            color: var(--text-main);
        }

        /* Fullscreen Editor */
        .fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            background-color: var(--bg-card);
        }

        .fullscreen .editor-container {
            height: 100vh;
        }

        .fullscreen-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            z-index: 10;
            background-color: var(--bg-main);
            color: var(--text-main);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .fullscreen-btn:hover {
            background-color: var(--primary);
            color: white;
        }

        /* Multi-file Upload */
        .file-list-preview {
            margin-top: 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .file-list-preview-header {
            padding: 0.5rem 1rem;
            background-color: var(--bg-main);
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-main);
        }

        .file-list-preview-body {
            max-height: 200px;
            overflow-y: auto;
        }

        .file-preview-item {
            padding: 0.5rem 1rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.875rem;
        }

        .file-preview-item:last-child {
            border-bottom: none;
        }

        .file-preview-name {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-main);
        }

        .file-preview-size {
            color: var(--text-muted);
        }

        .file-preview-remove {
            color: var(--danger);
            cursor: pointer;
            font-size: 1rem;
        }

        /* Chat AI Styles */
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 100%;
            background-color: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .chat-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: var(--primary);
            color: white;
        }

        .chat-title {
            font-weight: 600;
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .chat-body {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-height: calc(100vh - 300px);
            background-color: var(--bg-main);
        }

        .chat-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            background-color: var(--bg-card);
        }

        .chat-message {
            display: flex;
            gap: 1rem;
            max-width: 80%;
        }

        .chat-message-user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .chat-message-ai {
            align-self: flex-start;
        }

        .chat-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .chat-avatar-user {
            background-color: var(--primary);
            color: white;
        }

        .chat-avatar-ai {
            background-color: var(--info);
            color: white;
        }

        .chat-bubble {
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            position: relative;
        }

        .chat-bubble-user {
            background-color: var(--primary);
            color: white;
            border-bottom-right-radius: 0;
        }

        .chat-bubble-ai {
            background-color: var(--bg-card);
            color: var(--text-main);
            border-bottom-left-radius: 0;
        }

        .chat-content {
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .chat-time {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 0.25rem;
            text-align: right;
        }

        .chat-time-ai {
            color: var(--text-muted);
        }

        .chat-form {
            display: flex;
            gap: 0.5rem;
        }

        .chat-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border-radius: 2rem;
            border: 1px solid var(--border);
            outline: none;
            transition: border-color 0.2s;
            background-color: var(--bg-main);
            color: var(--text-main);
        }

        .chat-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
        }

        .chat-send {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
            border: none;
            outline: none;
        }

        .chat-send:hover {
            background-color: var(--primary-dark);
        }

        .chat-send:disabled {
            background-color: var(--gray);
            cursor: not-allowed;
        }

        .chat-attachment {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background-color: var(--bg-main);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
            border: 1px solid var(--border);
        }

        .chat-attachment:hover {
            background-color: var(--bg-main);
            color: var(--primary);
        }

        .chat-attachment input {
            display: none;
        }

        .chat-typing {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
        }

        .typing-dots {
            display: flex;
            gap: 0.25rem;
        }

        .typing-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background-color: var(--text-muted);
            animation: typingAnimation 1.5s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) {
            animation-delay: 0s;
        }

        .typing-dot:nth-child(2) {
            animation-delay: 0.3s;
        }

        .typing-dot:nth-child(3) {
            animation-delay: 0.6s;
        }

        @keyframes typingAnimation {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
        }

        .chat-file {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            background-color: var(--bg-main);
            border-radius: var(--radius);
            margin-top: 0.5rem;
        }

        .chat-file-icon {
            font-size: 1.25rem;
            color: var(--primary);
        }

        .chat-file-info {
            flex: 1;
            overflow: hidden;
        }

        .chat-file-name {
            font-size: 0.875rem;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-main);
        }

        .chat-file-size {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .chat-file-remove {
            color: var(--danger);
            cursor: pointer;
            font-size: 1rem;
        }

        .chat-file-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: var(--radius);
            margin-top: 0.5rem;
        }

        .chat-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
            text-align: center;
            padding: 2rem;
        }

        .chat-empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--text-muted);
        }

        .chat-empty-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }

        .chat-empty-text {
            font-size: 0.875rem;
            max-width: 300px;
            color: var(--text-muted);
        }

        .chat-error {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 0.75rem 1rem;
            border-radius: var(--radius);
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chat-error-icon {
            font-size: 1.25rem;
        }

        .chat-error-text {
            font-size: 0.875rem;
            flex: 1;
        }

        .chat-error-retry {
            background-color: #b91c1c;
            color: white;
            border: none;
            border-radius: var(--radius);
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            cursor: pointer;
        }

        .chat-error-retry:hover {
            background-color: #991b1b;
        }

        /* Code Block Styles */
        .code-block {
            background-color: #1e293b;
            border-radius: var(--radius);
            margin: 1rem 0;
            overflow: hidden;
        }

        .code-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 1rem;
            background-color: #0f172a;
            color: #94a3b8;
            font-size: 0.75rem;
            border-bottom: 1px solid #334155;
        }

        .code-language {
            font-family: 'Fira Code', monospace;
            text-transform: uppercase;
        }

        .code-copy {
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.2s;
        }

        .code-copy:hover {
            color: white;
        }

        .code-block pre {
            margin: 0;
            padding: 1rem;
            overflow-x: auto;
            background-color: #1e293b;
        }

        .code-block code {
            font-family: 'Fira Code', monospace;
            font-size: 0.875rem;
            color: #f8fafc;
            line-height: 1.6;
        }

        .chat-bubble-user .code-block {
            background-color: #3730a3;
        }

        .chat-bubble-user .code-header {
            background-color: #312e81;
            border-bottom: 1px solid #4338ca;
        }

        .chat-bubble-user .code-block pre {
            background-color: #3730a3;
        }
    </style>
</head>
<body>
    <?php if (!$authenticated): ?>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2>Eclipse File Manager</h2>
                <p>Please login to continue</p>
            </div>
            <div class="login-body">
                <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?php echo $_SESSION['alert']['type']; ?>">
                    <i class="fas fa-<?php echo $_SESSION['alert']['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <span><?php echo $_SESSION['alert']['message']; ?></span>
                </div>
                <?php unset($_SESSION['alert']); endif; ?>
                
                <form method="post">
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control" placeholder="Enter username" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login</span>
                    </button>
                </form>
            </div>
            <div class="login-footer">
                <p class="text-muted text-sm">Eclipse File Manager</p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="toast-container" id="toastContainer"></div>
    
    <div class="container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-folder"></i>
                    <span>Eclipse File Manager</span>
                </div>
                <button class="btn btn-sm btn-icon btn-light mobile-toggle" id="closeSidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="sidebar-content">
                <div class="user-info mb-4">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <form action="" method="get">
                        <input type="hidden" name="path" value="<?php echo htmlspecialchars($path); ?>">
                        <input type="text" name="search" class="search-input" placeholder="Search files..." value="<?php echo htmlspecialchars($search_query); ?>">
                    </form>
                </div>
                
                <div class="card mb-4">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <i class="fas fa-hdd text-primary" style="font-size: 1.5rem;"></i>
                            <div>
                                <div class="text-sm text-muted">Storage</div>
                                <div class="text-sm">
                                    <?php
                                    $totalSpace = disk_total_space($path);
                                    $freeSpace = disk_free_space($path);
                                    $usedSpace = $totalSpace - $freeSpace;
                                    $usedPercent = round(($usedSpace / $totalSpace) * 100);
                                    echo formatSize($usedSpace) . ' / ' . formatSize($totalSpace);
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div style="height: 6px; background-color: var(--border); border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo $usedPercent; ?>%; background-color: var(--primary);"></div>
                        </div>
                        <div class="text-xs text-right text-muted mt-1"><?php echo $usedPercent; ?>% used</div>
                    </div>
                </div>
                
                <!-- Quick Access Menu -->
                <div class="sidebar-menu">
                    <div class="sidebar-menu-title">Quick Access</div>
                    <ul class="sidebar-menu-items">
                        <li class="sidebar-menu-item">
                            <a href="?path=<?php echo urlencode(getcwd()); ?>" class="sidebar-menu-link">
                                <span class="sidebar-menu-icon"><i class="fas fa-home"></i></span>
                                <span>Home Directory</span>
                            </a>
                        </li>
                        <?php if (is_dir(getcwd() . '/uploads')): ?>
                        <li class="sidebar-menu-item">
                            <a href="?path=<?php echo urlencode(getcwd() . '/uploads'); ?>" class="sidebar-menu-link">
                                <span class="sidebar-menu-icon"><i class="fas fa-upload"></i></span>
                                <span>Uploads</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="sidebar-menu-item">
                            <a href="?path=<?php echo urlencode($path); ?>&view=chat" class="sidebar-menu-link <?php echo isset($_GET['view']) && $_GET['view'] === 'chat' ? 'active' : ''; ?>">
                                <span class="sidebar-menu-icon"><i class="fas fa-robot"></i></span>
                                <span>Chat AI</span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Tools Menu -->
                <div class="sidebar-menu">
                    <div class="sidebar-menu-title">Tools</div>
                    <ul class="sidebar-menu-items">
                        <li class="sidebar-menu-item">
                            <a href="#" class="sidebar-menu-link" id="openNewFolderModalBtn">
                                <span class="sidebar-menu-icon"><i class="fas fa-folder-plus"></i></span>
                                <span>New Folder</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="#" class="sidebar-menu-link" id="openNewFileModalBtn">
                                <span class="sidebar-menu-icon">
                                    <i class="fas fa-file"></i>
                                    <i class="fas fa-plus" style="font-size: 0.7em; position: absolute; margin-left: -0.5em; margin-top: -0.5em;"></i>
                                </span>
                                <span>New File</span>
                            </a>
                        </li>
                        <li class="sidebar-menu-item">
                            <a href="#" class="sidebar-menu-link" id="openUploadModalBtn">
                                <span class="sidebar-menu-icon"><i class="fas fa-cloud-upload-alt"></i></span>
                                <span>Upload Files</span>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Theme Toggle -->
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-<?php echo $theme === 'dark' ? 'sun' : 'moon'; ?>"></i>
                    <span><?php echo $theme === 'dark' ? 'Light Mode' : 'Dark Mode'; ?></span>
                </button>
            </div>
            <div class="sidebar-footer">
                <a href="?logout=true" class="btn btn-danger btn-block">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="topbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-sm btn-icon btn-light mobile-toggle" id="openSidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="breadcrumb">
                        <?php
                        if (isset($_GET['view']) && $_GET['view'] === 'chat') {
                            echo '<div class="breadcrumb-item">';
                            echo '<a href="?path=' . urlencode($path) . '" class="breadcrumb-link"><i class="fas fa-home"></i></a>';
                            echo '</div>';
                            echo '<span class="current-path">/Chat AI</span>';
                        } else {
                            $parts = explode('/', $path);
                            $breadcrumb = '';
                            
                            echo '<div class="breadcrumb-item">';
                            echo '<a href="?path=/" class="breadcrumb-link"><i class="fas fa-home"></i></a>';
                            echo '</div>';
                            
                            foreach ($parts as $i => $part) {
                                if (empty($part)) continue;
                                
                                $breadcrumb .= '/' . $part;
                                
                                if ($i === count($parts) - 1) {
                                    echo '<span class="current-path">/' . htmlspecialchars($part) . '</span>';
                                } else {
                                    echo '<a href="?path=' . urlencode($breadcrumb) . '" class="breadcrumb-link">/' . htmlspecialchars($part) . '</a>';
                                }
                            }
                        }
                        ?>
                    </div>
                </div>
                <div>
                    <?php if (!isset($_GET['view']) || $_GET['view'] !== 'chat'): ?>
                    <button class="btn btn-sm btn-primary" id="openUploadModalTopBtn">
                        <i class="fas fa-upload"></i>
                        <span>Upload</span>
                    </button>
                    <?php else: ?>
                    <button class="btn btn-sm btn-primary" id="newChatSession">
                        <i class="fas fa-plus"></i>
                        <span>New Chat</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="content">
                <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?php echo $_SESSION['alert']['type']; ?>">
                    <i class="fas fa-<?php echo $_SESSION['alert']['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <span><?php echo $_SESSION['alert']['message']; ?></span>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        showToast('<?php echo $_SESSION['alert']['type']; ?>', 
                                '<?php echo $_SESSION['alert']['type'] === 'success' ? 'Success' : 'Error'; ?>', 
                                '<?php echo addslashes($_SESSION['alert']['message']); ?>');
                    });
                </script>
                <?php unset($_SESSION['alert']); endif; ?>

                <?php if (!empty($search_query)): ?>
                <!-- Search Results -->
                <div class="search-results">
                    <div class="search-results-header">
                        Search results for "<?php echo htmlspecialchars($search_query); ?>" (<?php echo count($search_results); ?> found)
                    </div>
                    <div class="search-results-body">
                        <?php if (empty($search_results)): ?>
                        <div class="text-center p-3 text-muted">No results found</div>
                        <?php else: ?>
                        <?php foreach ($search_results as $result): ?>
                        <div class="search-result-item">
                            <a href="?path=<?php echo urlencode(dirname($result['path'])); ?><?php echo $result['is_dir'] ? '' : '&edit=' . urlencode(basename($result['path'])); ?>" class="search-result-link">
                                <div class="search-result-icon <?php echo $result['is_dir'] ? 'folder' : getFileIcon(basename($result['path'])); ?>" style="background-color: <?php echo $result['is_dir'] ? 'var(--warning)' : ''; ?>">
                                    <i class="fas fa-<?php echo $result['is_dir'] ? 'folder' : 'file'; ?>"></i>
                                </div>
                                <div>
                                    <div class="search-result-name"><?php echo htmlspecialchars(basename($result['path'])); ?></div>
                                    <div class="search-result-path"><?php echo htmlspecialchars(dirname($result['path'])); ?></div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['view']) && $_GET['view'] === 'chat'): ?>
                <!-- Chat AI Interface -->
                <div class="chat-container">
                    <div class="chat-header">
                        <div class="chat-title">
                            <i class="fas fa-robot"></i>
                            <span>EclipseAI Assistant</span>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-light" id="newChatBtn">
                                <i class="fas fa-plus"></i>
                                <span>New Chat</span>
                            </button>
                        </div>
                    </div>
                    <div class="chat-body" id="chatMessages">
                        <div class="chat-empty" id="chatEmpty">
                            <div class="chat-empty-icon">
                                <i class="fas fa-robot"></i>
                            </div>
                            <div class="chat-empty-title">EclipseAI Assistant</div>
                            <div class="chat-empty-text">
                                Hello! I'm EclipseAI, your AI assistant. I can help you solve problems, answer questions, and provide information on various topics. How can I assist you today?
                            </div>
                        </div>
                    </div>
                    <div class="chat-footer">
                        <form id="chatForm" class="chat-form">
                            <label class="chat-attachment">
                                <i class="fas fa-paperclip"></i>
                                <input type="file" id="chatFile" accept="image/*,.pdf,.doc,.docx,.txt">
                            </label>
                            <input type="text" id="chatInput" class="chat-input" placeholder="Type your message..." autocomplete="off">
                            <button type="submit" class="chat-send" id="chatSend">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                        <div id="chatFilePreview"></div>
                        <div id="chatTyping" class="chat-typing" style="display: none;">
                            <span>EclipseAI is typing</span>
                            <div class="typing-dots">
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                            </div>
                        </div>
                        <div id="chatError" class="chat-error" style="display: none;">
                            <div class="chat-error-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="chat-error-text">An error occurred. Please try again.</div>
                            <button class="chat-error-retry" id="chatRetry">Retry</button>
                        </div>
                    </div>
                </div>
                <?php elseif (isset($_GET['preview']) && !empty($_GET['preview'])): ?>
                <!-- File Preview -->
                <?php
                $preview_file = realpath($path . '/' . $_GET['preview']);
                if (strpos($preview_file, $path) === 0 && is_file($preview_file) && isViewable(basename($preview_file))):
                    $filename = basename($preview_file);
                    $mime_type = getMimeType($filename);
                    $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                ?>
                <div class="preview-container">
                    <div class="preview-header">
                        <div class="preview-title">
                            <i class="fas fa-eye"></i>
                            <span>Previewing: <?php echo htmlspecialchars($filename); ?></span>
                        </div>
                        <div>
                            <a href="<?php echo htmlspecialchars($preview_file); ?>" download class="btn btn-sm btn-primary">
                                <i class="fas fa-download"></i>
                                <span>Download</span>
                            </a>
                            <a href="?path=<?php echo urlencode($path); ?>&edit=<?php echo urlencode($filename); ?>" class="btn btn-sm btn-light">
                                <i class="fas fa-edit"></i>
                                <span>Edit</span>
                            </a>
                        </div>
                    </div>
                    <div class="preview-body">
                        <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'svg'])): ?>
                        <img src="<?php echo htmlspecialchars($preview_file); ?>" alt="<?php echo htmlspecialchars($filename); ?>" class="preview-image">
                        <?php elseif ($file_ext === 'pdf'): ?>
                        <iframe src="<?php echo htmlspecialchars($preview_file); ?>" class="preview-pdf"></iframe>
                        <?php else: ?>
                        <pre class="preview-text"><?php echo htmlspecialchars(file_get_contents($preview_file)); ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <!-- File Manager Interface -->
                <div class="action-buttons">
                    <button class="btn btn-light" id="openNewFolderModalAction">
                        <i class="fas fa-folder-plus"></i>
                        <span>New Folder</span>
                    </button>
                    <button class="btn btn-light" id="openNewFileModalAction">
                        <i class="fas fa-plus"></i>
                        <i class="fas fa-file"></i>
                        <span>New File</span>
                    </button>
                    <button class="btn btn-light" id="openUploadModalAction">
                        <i class="fas fa-upload"></i>
                        <span>Upload Files</span>
                    </button>
                    <button class="btn btn-light" id="refreshBtn">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh</span>
                    </button>
                </div>

                <div class="file-list">
                    <div class="file-list-header">
                        <div>Name</div>
                        <div>Size</div>
                        <div>Permissions</div>
                        <div>Actions</div>
                    </div>
                    <div class="file-list-body">
                        <?php if ($path !== '/'): ?>
                        <div class="file-item">
                            <div class="file-name">
                                <div class="file-icon folder">
                                    <i class="fas fa-level-up-alt"></i>
                                </div>
                                <a href="?path=<?php echo urlencode(dirname($path)); ?>" class="file-link">..</a>
                            </div>
                            <div class="file-size">-</div>
                            <div class="file-perm">-</div>
                            <div class="file-actions">-</div>
                        </div>
                        <?php endif; ?>

                        <?php
                        $items = scandir($path);
                        $folders = [];
                        $files = [];
                        
                        foreach ($items as $item) {
                            if ($item === '.' || $item === '..') continue;
                            
                            $fullPath = $path . '/' . $item;
                            if (is_dir($fullPath)) {
                                $folders[] = $item;
                            } else {
                                $files[] = $item;
                            }
                        }
                        
                        sort($folders);
                        sort($files);
                        
                        foreach ($folders as $folder) {
                            $fullPath = $path . '/' . $folder;
                            ?>
                            <div class="file-item">
                                <div class="file-name">
                                    <div class="file-icon folder">
                                        <i class="fas fa-folder"></i>
                                    </div>
                                    <a href="?path=<?php echo urlencode($fullPath); ?>" class="file-link"><?php echo htmlspecialchars($folder); ?></a>
                                </div>
                                <div class="file-size">-</div>
                                <div class="file-perm"><?php echo substr(sprintf('%o', fileperms($fullPath)), -4); ?></div>
                                <div class="file-actions">
                                    <button class="btn btn-sm btn-outline rename-btn" data-name="<?php echo htmlspecialchars($folder); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline delete-btn" data-name="<?php echo htmlspecialchars($folder); ?>" data-path="?path=<?php echo urlencode($path); ?>&delete=<?php echo urlencode($folder); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php
                        }
                        
                        foreach ($files as $file) {
                            $fullPath = $path . '/' . $file;
                            $fileSize = filesize($fullPath);
                            $fileIcon = getFileIcon($file);
                            ?>
                            <div class="file-item">
                                <div class="file-name">
                                    <div class="file-icon <?php echo $fileIcon; ?>">
                                        <i class="fas fa-<?php echo $fileIcon === 'folder' ? 'folder' : 'file'; ?>"></i>
                                    </div>
                                    <?php if (isViewable($file)): ?>
                                    <a href="?path=<?php echo urlencode($path); ?>&preview=<?php echo urlencode($file); ?>" class="file-link"><?php echo htmlspecialchars($file); ?></a>
                                    <?php else: ?>
                                    <a href="?path=<?php echo urlencode($path); ?>&edit=<?php echo urlencode($file); ?>" class="file-link"><?php echo htmlspecialchars($file); ?></a>
                                    <?php endif; ?>
                                </div>
                                <div class="file-size"><?php echo formatSize($fileSize); ?></div>
                                <div class="file-perm"><?php echo substr(sprintf('%o', fileperms($fullPath)), -4); ?></div>
                                <div class="file-actions">
                                    <button class="btn btn-sm btn-outline rename-btn" data-name="<?php echo htmlspecialchars($file); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?path=<?php echo urlencode($path); ?>&edit=<?php echo urlencode($file); ?>" class="btn btn-sm btn-outline">
                                        <i class="fas fa-code"></i>
                                    </a>
                                    <a href="<?php echo htmlspecialchars($fullPath); ?>" download class="btn btn-sm btn-outline">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline delete-btn" data-name="<?php echo htmlspecialchars($file); ?>" data-path="?path=<?php echo urlencode($path); ?>&delete=<?php echo urlencode($file); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php
                        }
                        
                        if (empty($folders) && empty($files)) {
                            echo '<div class="text-center p-3 text-muted">This folder is empty</div>';
                        }
                        ?>
                    </div>
                </div>

                <?php if (isset($_GET['edit'])): ?>
                <?php
                $edit = realpath($path . '/' . $_GET['edit']);
                if (strpos($edit, $path) === 0 && is_file($edit)):
                    $content = htmlspecialchars(file_get_contents($edit));
                    $filename = basename($edit);
                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $editorMode = getEditorMode($filename);
                ?>
                <div class="card mt-4" style="margin-bottom: 0;" id="editorCard">
                    <div class="card-header">
                        <div>Editing: <?php echo htmlspecialchars($filename); ?></div>
                        <div>
                            <button class="btn btn-sm btn-light toggle-theme-btn">
                                <i class="fas fa-moon"></i>
                                <span>Toggle Theme</span>
                            </button>
                            <button class="btn btn-sm btn-light fullscreen-toggle">
                                <i class="fas fa-expand"></i>
                                <span>Fullscreen</span>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="editor-container">
                            <form method="post">
                                <div class="editor-body">
                                    <textarea name="content" id="codeEditor" data-mode="<?php echo $editorMode; ?>"><?php echo $content; ?></textarea>
                                </div>
                                <div class="editor-footer">
                                    <div class="text-xs text-muted mt-2">
                                        Shortcuts: <kbd>Ctrl+S</kbd> Save, <kbd>Ctrl+F</kbd> Find, <kbd>Ctrl+Space</kbd> Autocomplete, <kbd>F11</kbd> Fullscreen
                                    </div>
                                    <input type="hidden" name="save_file" value="<?php echo htmlspecialchars(basename($edit)); ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i>
                                        <span>Save Changes</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <!-- Upload Modal -->
    <div class="modal-backdrop" id="uploadModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Upload Files</div>
                <button class="modal-close" data-dismiss="modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <div class="dropzone" id="dropzone">
                        <div class="dropzone-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="dropzone-text">Drag & drop files here or click to browse</div>
                        <div class="dropzone-hint">Maximum file size: 10MB</div>
                        <input type="file" name="upload[]" id="fileInput" style="display: none;" multiple>
                    </div>
                    <div id="selectedFiles" class="mt-4" style="display: none;">
                        <div class="file-list-preview">
                            <div class="file-list-preview-header">Selected Files</div>
                            <div class="file-list-preview-body" id="filePreviewList"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="submitUpload">Upload</button>
            </div>
        </div>
    </div>

    <!-- New Folder Modal -->
    <div class="modal-backdrop" id="newFolderModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Create New Folder</div>
                <button class="modal-close" data-dismiss="modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" id="newFolderForm">
                    <div class="form-group">
                        <label for="folderName" class="form-label">Folder Name</label>
                        <input type="text" name="new_folder" id="folderName" class="form-control" placeholder="Enter folder name" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="submitNewFolder">Create</button>
            </div>
        </div>
    </div>

    <!-- New File Modal -->
    <div class="modal-backdrop" id="newFileModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Create New File</div>
                <button class="modal-close" data-dismiss="modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" id="newFileForm">
                    <div class="form-group">
                        <label for="fileName" class="form-label">File Name</label>
                        <input type="text" name="new_file" id="fileName" class="form-control" placeholder="Enter file name" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="submitNewFile">Create</button>
            </div>
        </div>
    </div>

    <!-- Rename Modal -->
    <div class="modal-backdrop" id="renameModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Rename Item</div>
                <button class="modal-close" data-dismiss="modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" id="renameForm">
                    <div class="form-group">
                        <label for="newName" class="form-label">New Name</label>
                        <input type="hidden" name="rename_from" id="renameFrom">
                        <input type="text" name="rename_to" id="newName" class="form-control" placeholder="Enter new name" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="submitRename">Create</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-backdrop" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">Confirm Delete</div>
                <button class="modal-close" data-dismiss="modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteItemName"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-dismiss="modal">Cancel</button>
                <a href="#" class="btn btn-danger" id="confirmDelete">Delete</a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/markdown/markdown.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/sql/sql.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/yaml/yaml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/edit/matchbrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/edit/closebrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/edit/closetag.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/foldcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/foldgutter.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/brace-fold.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/xml-fold.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/comment-fold.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/indent-fold.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/markdown-fold.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/display/autorefresh.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/search/search.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/search/searchcursor.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/search/jump-to-line.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/dialog/dialog.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/hint/show-hint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/hint/xml-hint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/hint/html-hint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/hint/css-hint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/hint/javascript-hint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/hint/sql-hint.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/selection/active-line.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const sidebar = document.getElementById('sidebar');
            const openSidebarBtn = document.getElementById('openSidebar');
            const closeSidebarBtn = document.getElementById('closeSidebar');
            
            const uploadModal = document.getElementById('uploadModal');
            const newFolderModal = document.getElementById('newFolderModal');
            const newFileModal = document.getElementById('newFileModal');
            const renameModal = document.getElementById('renameModal');
            const deleteModal = document.getElementById('deleteModal');
            
            const uploadForm = document.getElementById('uploadForm');
            const newFolderForm = document.getElementById('newFolderForm');
            const newFileForm = document.getElementById('newFileForm');
            const renameForm = document.getElementById('renameForm');
            
            // Multiple buttons for the same action
            const openUploadModalBtns = [
                document.getElementById('openUploadModalBtn'),
                document.getElementById('openUploadModalTopBtn'),
                document.getElementById('openUploadModalAction')
            ];
            
            const openNewFolderModalBtns = [
                document.getElementById('openNewFolderModalBtn'),
                document.getElementById('openNewFolderModalAction')
            ];
            
            const openNewFileModalBtns = [
                document.getElementById('openNewFileModalBtn'),
                document.getElementById('openNewFileModalAction')
            ];
            
            const submitUploadBtn = document.getElementById('submitUpload');
            const submitNewFolderBtn = document.getElementById('submitNewFolder');
            const submitNewFileBtn = document.getElementById('submitNewFile');
            const submitRenameBtn = document.getElementById('submitRename');
            const confirmDeleteBtn = document.getElementById('confirmDelete');
            const refreshBtn = document.getElementById('refreshBtn');
            
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('fileInput');
            const selectedFiles = document.getElementById('selectedFiles');
            const filePreviewList = document.getElementById('filePreviewList');
            
            const codeEditor = document.getElementById('codeEditor');
            const toggleThemeBtn = document.querySelector('.toggle-theme-btn');
            const fullscreenToggleBtn = document.querySelector('.fullscreen-toggle');
            const editorCard = document.getElementById('editorCard');
            
            const toastContainer = document.getElementById('toastContainer');
            const themeToggle = document.getElementById('themeToggle');
            
            // Chat AI elements
            const chatMessages = document.getElementById('chatMessages');
            const chatForm = document.getElementById('chatForm');
            const chatInput = document.getElementById('chatInput');
            const chatSend = document.getElementById('chatSend');
            const chatFile = document.getElementById('chatFile');
            const chatFilePreview = document.getElementById('chatFilePreview');
            const chatTyping = document.getElementById('chatTyping');
            const chatError = document.getElementById('chatError');
            const chatRetry = document.getElementById('chatRetry');
            const chatEmpty = document.getElementById('chatEmpty');
            const newChatBtn = document.getElementById('newChatBtn');
            const newChatSession = document.getElementById('newChatSession');
            
            // Chat session ID
            let chatSessionId = '<?php echo $_SESSION['chat_session_id']; ?>';
            let lastMessage = null;
            let chatHistory = [];
            
            // Initialize CodeMirror editor
            let editor;
            if (codeEditor) {
    const mode = codeEditor.getAttribute('data-mode') || 'text/plain';
    editor = CodeMirror.fromTextArea(codeEditor, {
        mode: mode,
        theme: '<?php echo $theme === 'dark' ? 'dracula' : 'eclipse'; ?>',
        lineNumbers: true,
        lineWrapping: true,
        autoCloseBrackets: true,
        autoCloseTags: true,
        matchBrackets: true,
        indentUnit: 4,
        tabSize: 4,
        indentWithTabs: false,
        extraKeys: {
            "Ctrl-Space": "autocomplete",
            "Ctrl-/": "toggleComment",
            "Ctrl-F": "findPersistent",
            "F11": function(cm) {
                toggleFullscreen();
            },
            "Esc": function(cm) {
                if (editorCard.classList.contains('fullscreen')) {
                    toggleFullscreen();
                }
            }
        },
        foldGutter: true,
        gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
        styleActiveLine: true,
        autoRefresh: true,
        viewportMargin: Infinity
    });
    
    // JANGAN set ukuran editor, biarkan mengisi container
    
    // Force refresh editor beberapa kali
    setTimeout(() => editor.refresh(), 100);
    setTimeout(() => editor.refresh(), 500);
    setTimeout(() => editor.refresh(), 1000);
    setTimeout(() => editor.refresh(), 2000);
}
            
            // Sidebar toggle
            if (openSidebarBtn) {
                openSidebarBtn.addEventListener('click', () => {
                    sidebar.classList.add('show');
                });
            }
            
            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', () => {
                    sidebar.classList.remove('show');
                });
            }
            
            // Modal functions
            function openModal(modal) {
                if (!modal) return;
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
            
            function closeModal(modal) {
                if (!modal) return;
                modal.classList.remove('show');
                document.body.style.overflow = '';
            }
            
            // Close modal when clicking outside
            document.querySelectorAll('.modal-backdrop').forEach(modal => {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        closeModal(modal);
                    }
                });
            });
            
            // Close modal when clicking close button
            document.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const modal = btn.closest('.modal-backdrop');
                    closeModal(modal);
                });
            });
            
            // Open upload modal
            openUploadModalBtns.forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', () => {
                        openModal(uploadModal);
                    });
                }
            });
            
            // Open new folder modal
            openNewFolderModalBtns.forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', () => {
                        openModal(newFolderModal);
                    });
                }
            });
            
            // Open new file modal
            openNewFileModalBtns.forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', () => {
                        openModal(newFileModal);
                    });
                }
            });
            
            // Submit upload form
            if (submitUploadBtn) {
                submitUploadBtn.addEventListener('click', () => {
                    if (fileInput.files.length > 0) {
                        uploadForm.submit();
                    } else {
                        showToast('warning', 'Warning', 'Please select at least one file to upload.');
                    }
                });
            }
            
            // Submit new folder form
            if (submitNewFolderBtn) {
                submitNewFolderBtn.addEventListener('click', () => {
                    if (newFolderForm.checkValidity()) {
                        newFolderForm.submit();
                    } else {
                        showToast('warning', 'Warning', 'Please enter a folder name.');
                    }
                });
            }
            
            // Submit new file form
            if (submitNewFileBtn) {
                submitNewFileBtn.addEventListener('click', () => {
                    if (newFileForm.checkValidity()) {
                        newFileForm.submit();
                    } else {
                        showToast('warning', 'Warning', 'Please enter a file name.');
                    }
                });
            }
            
            // Submit rename form
            if (submitRenameBtn) {
                submitRenameBtn.addEventListener('click', () => {
                    if (renameForm.checkValidity()) {
                        renameForm.submit();
                    } else {
                        showToast('warning', 'Warning', 'Please enter a new name.');
                    }
                });
            }
            
            // Refresh page
            if (refreshBtn) {
                refreshBtn.addEventListener('click', () => {
                    window.location.reload();
                });
            }
            
            // Dropzone functionality
            if (dropzone) {
                dropzone.addEventListener('click', () => {
                    fileInput.click();
                });
                
                dropzone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    dropzone.classList.add('dragover');
                });
                
                dropzone.addEventListener('dragleave', () => {
                    dropzone.classList.remove('dragover');
                });
                
                dropzone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    dropzone.classList.remove('dragover');
                    
                    if (e.dataTransfer.files.length) {
                        fileInput.files = e.dataTransfer.files;
                        updateSelectedFiles();
                    }
                });
                
                fileInput.addEventListener('change', updateSelectedFiles);
                
                function updateSelectedFiles() {
                    if (fileInput.files.length) {
                        filePreviewList.innerHTML = '';
                        
                        for (let i = 0; i < fileInput.files.length; i++) {
                            const file = fileInput.files[i];
                            const fileItem = document.createElement('div');
                            fileItem.className = 'file-preview-item';
                            fileItem.innerHTML = `
                                <div class="file-preview-name">
                                    <i class="fas fa-file"></i>
                                    <span>${file.name}</span>
                                </div>
                                <div class="file-preview-size">${formatFileSize(file.size)}</div>
                            `;
                            filePreviewList.appendChild(fileItem);
                        }
                        
                        selectedFiles.style.display = 'block';
                    } else {
                        selectedFiles.style.display = 'none';
                    }
                }
                
                function formatFileSize(bytes) {
                    if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
                    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
                    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
                    return bytes + ' B';
                }
            }
            
            // Rename functionality
            document.querySelectorAll('.rename-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const name = btn.getAttribute('data-name');
                    document.getElementById('renameFrom').value = name;
                    document.getElementById('newName').value = name;
                    openModal(renameModal);
                });
            });
            
            // Delete functionality
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const name = btn.getAttribute('data-name');
                    const deleteUrl = btn.getAttribute('data-path');
                    
                    document.getElementById('deleteItemName').textContent = name;
                    document.getElementById('confirmDelete').setAttribute('href', deleteUrl);
                    
                    openModal(deleteModal);
                });
            });
            
            // Theme toggle functionality
            if (toggleThemeBtn && editor) {
                toggleThemeBtn.addEventListener('click', () => {
                    const currentTheme = editor.getOption('theme');
                    const newTheme = currentTheme === 'eclipse' ? 'dracula' : 'eclipse';
                    editor.setOption('theme', newTheme);
                    
                    const icon = toggleThemeBtn.querySelector('i');
                    if (newTheme === 'dracula') {
                        icon.classList.remove('fa-moon');
                        icon.classList.add('fa-sun');
                    } else {
                        icon.classList.remove('fa-sun');
                        icon.classList.add('fa-moon');
                    }
                });
            }
            
            // Global theme toggle
            if (themeToggle) {
                themeToggle.addEventListener('click', () => {
                    const currentTheme = document.documentElement.getAttribute('data-theme');
                    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                    
                    document.documentElement.setAttribute('data-theme', newTheme);
                    
                    // Update button icon and text
                    const icon = themeToggle.querySelector('i');
                    const text = themeToggle.querySelector('span');
                    
                    if (newTheme === 'dark') {
                        icon.classList.remove('fa-moon');
                        icon.classList.add('fa-sun');
                        text.textContent = 'Light Mode';
                    } else {
                        icon.classList.remove('fa-sun');
                        icon.classList.add('fa-moon');
                        text.textContent = 'Dark Mode';
                    }
                    
                    // Save theme preference in cookie
                    document.cookie = `theme=${newTheme}; path=/; max-age=31536000`;
                    
                    // Update editor theme if editor exists
                    if (editor) {
                        editor.setOption('theme', newTheme === 'dark' ? 'dracula' : 'eclipse');
                    }
                });
            }
            
            // Toast notification function
            window.showToast = function(type, title, message, duration = 5000) {
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                
                let icon = 'info-circle';
                if (type === 'success') icon = 'check-circle';
                if (type === 'danger') icon = 'exclamation-circle';
                if (type === 'warning') icon = 'exclamation-triangle';
                
                toast.innerHTML = `
                    <div class="toast-icon">
                        <i class="fas fa-${icon}"></i>
                    </div>
                    <div class="toast-content">
                        <div class="toast-title">${title}</div>
                        <div class="toast-message">${message}</div>
                    </div>
                    <button class="toast-close">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="toast-progress" style="animation-duration: ${duration}ms"></div>
                `;
                
                toastContainer.appendChild(toast);
                
                toast.querySelector('.toast-close').addEventListener('click', () => {
                    toast.classList.add('hide');
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                });
                
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.classList.add('hide');
                        setTimeout(() => {
                            if (toast.parentNode) {
                                toast.remove();
                            }
                        }, 300);
                    }
                }, duration);
            };
            
            // Check if mobile
            function isMobile() {
                return window.innerWidth < 768;
            }
            
            // Close sidebar on mobile when clicking a link
            if (isMobile()) {
                document.querySelectorAll('.sidebar a').forEach(link => {
                    link.addEventListener('click', () => {
                        sidebar.classList.remove('show');
                    });
                });
            }
            
            // Resize handler
            window.addEventListener('resize', () => {
                if (!isMobile()) {
                    sidebar.classList.remove('show');
                }
                
                // Resize editor dengan tinggi yang lebih besar
                if (editor) {
                    const editorContainer = document.querySelector('.editor-container');
                    if (editorContainer) {
                        const availableHeight = window.innerHeight - 150;
                        editorContainer.style.height = availableHeight + 'px';
            
                        // PENTING: Jangan set ukuran editor, biarkan mengisi container
                        setTimeout(() => {
                            editor.refresh();
                        }, 100);
                    }
                }
            });
            
            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                // Close modals with Escape
                if (e.key === 'Escape') {
                    document.querySelectorAll('.modal-backdrop.show').forEach(modal => {
                        closeModal(modal);
                    });
                }
                
                // Save file with Ctrl+S
                if (e.key === 's' && (e.ctrlKey || e.metaKey) && editor) {
                    if (document.querySelector('form[method="post"] button[type="submit"]')) {
                        e.preventDefault();
                        document.querySelector('form[method="post"]').submit();
                    }
                }
            });
            
            // Chat AI functionality
            if (chatForm) {
                let uploadedFile = null;
                
                // Initialize code input handling
                handleUserCodeInput();
                
                // Handle file uploads for chat
                chatFile.addEventListener('change', async (e) => {
                    if (chatFile.files.length > 0) {
                        const file = chatFile.files[0];
                        
                        // Preview file
                        chatFilePreview.innerHTML = `
                            <div class="chat-file">
                                <div class="chat-file-icon">
                                    <i class="fas fa-${file.type.startsWith('image/') ? 'image' : 'file'}"></i>
                                </div>
                                <div class="chat-file-info">
                                    <div class="chat-file-name">${file.name}</div>
                                    <div class="chat-file-size">${formatFileSize(file.size)}</div>
                                </div>
                                <div class="chat-file-remove" id="removeFile">
                                    <i class="fas fa-times"></i>
                                </div>
                            </div>
                        `;
                        
                        // If it's an image, show preview
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                chatFilePreview.innerHTML += `
                                    <img src="${e.target.result}" class="chat-file-preview" alt="${file.name}">
                                `;
                            };
                            reader.readAsDataURL(file);
                        }
                        
                        // Upload file to server
                        const formData = new FormData();
                        formData.append('file', file);
                        
                        try {
                            // Simulate file upload
                            uploadedFile = {
                                name: file.name,
                                size: file.size,
                                type: file.type,
                                url: URL.createObjectURL(file) // In a real implementation, this would be the URL from the server
                            };
                        } catch (error) {
                            console.error('Error uploading file:', error);
                            showToast('danger', 'Error', 'Failed to upload file. Please try again.');
                        }
                        
                        // Add event listener to remove file button
                        document.getElementById('removeFile').addEventListener('click', () => {
                            chatFilePreview.innerHTML = '';
                            chatFile.value = '';
                            uploadedFile = null;
                        });
                    }
                });
                
                // Handle chat form submission
                chatForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const message = chatInput.value.trim();
                    if (!message && !uploadedFile) return;
                    
                    // Add user message to chat
                    addMessage(message, 'user');
                    
                    // Clear input
                    chatInput.value = '';
                    
                    // Show typing indicator
                    chatTyping.style.display = 'flex';
                    chatEmpty.style.display = 'none';
                    
                    try {
                        // Send message to AI
                        const fileUrl = uploadedFile ? uploadedFile.url : null;
                        
                        // Make API request
                        const response = await sendMessageToAI(message, fileUrl);
                        
                        // Add AI response to chat
                        addMessage(response.text, 'ai');
                        
                        // Clear file preview and uploaded file
                        chatFilePreview.innerHTML = '';
                        chatFile.value = '';
                        uploadedFile = null;
                    } catch (error) {
                        console.error('Error sending message:', error);
                        chatError.style.display = 'flex';
                    }
                    
                    // Hide typing indicator
                    chatTyping.style.display = 'none';
                });
                
                // Retry button
                if (chatRetry) {
                    chatRetry.addEventListener('click', async () => {
                        chatError.style.display = 'none';
                        
                        if (lastMessage) {
                            // Show typing indicator
                            chatTyping.style.display = 'flex';
                            
                            try {
                                // Retry sending the last message
                                const response = await sendMessageToAI(lastMessage.text, lastMessage.fileUrl);
                                
                                // Add AI response to chat
                                addMessage(response.text, 'ai');
                            } catch (error) {
                                console.error('Error retrying message:', error);
                                chatError.style.display = 'flex';
                            }
                            
                            // Hide typing indicator
                            chatTyping.style.display = 'none';
                        }
                    });
                }
                
                // New chat button
                if (newChatBtn) {
                    newChatBtn.addEventListener('click', () => {
                        // Generate new session ID
                        chatSessionId = generateSessionId();
                        
                        // Clear chat history
                        chatHistory = [];
                        
                        // Clear chat messages
                        chatMessages.innerHTML = '';
                        chatEmpty.style.display = 'flex';
                        
                        // Show toast
                        showToast('info', 'New Chat', 'Started a new conversation with EclipseAI.');
                    });
                }

                // New chat session button
                if (newChatSession) {
                    newChatSession.addEventListener('click', () => {
                        // Generate new session ID
                        chatSessionId = generateSessionId();
                        
                        // Clear chat history
                        chatHistory = [];
                        
                        // Clear chat messages
                        chatMessages.innerHTML = '';
                        chatEmpty.style.display = 'flex';
                        
                        // Show toast
                        showToast('info', 'New Chat', 'Started a new conversation with EclipseAI.');
                    });
                }
                
                // Function to send message to AI
                async function sendMessageToAI(text, fileUrl = null) {
                    try {
                        // Store last message for retry
                        lastMessage = { text, fileUrl };
                        
                        // Add to chat history
                        chatHistory.push({ role: 'user', content: text });
                        
                        // Make API request
                        const response = await axios.get('https://api.eclair.web.id/api/ai-session', {
                            params: {
                                id: chatSessionId,
                                text: text,
                                prompt: 'Kamu adalah EclipseAI, Assisten AI Yang dapat membantu memecahkan masalah apapun!',
                                file: fileUrl || 'null',
                                apikey: 'kensdev'
                            }
                        });
                        
                        // Check if response data exists and has the expected structure
                        if (response.data && response.data.data && response.data.data.message) {
                            // Extract the message from the nested structure
                            const aiMessage = response.data.data.message;
                            
                            // Add AI response to chat history
                            chatHistory.push({ role: 'assistant', content: aiMessage });
                            
                            // Return in the format expected by the addMessage function
                            return { text: aiMessage };
                        } else {
                            // Handle empty or invalid response
                            throw new Error('Invalid response from AI service');
                        }
                    } catch (error) {
                        console.error('Error in AI request:', error);
                        throw error;
                    }
                }
                
                // Function to add message to chat
                function addMessage(text, sender) {
                    const messageElement = document.createElement('div');
                    messageElement.className = `chat-message chat-message-${sender}`;
                    
                    const now = new Date();
                    const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    
                    // Process code blocks for AI messages
                    let processedText = text;
                    if (sender === 'ai') {
                        // Replace code blocks with formatted HTML
                        processedText = processCodeBlocks(text);
                    }
                    
                    messageElement.innerHTML = `
                        <div class="chat-avatar chat-avatar-${sender}">
                            ${sender === 'user' ? 
                                '<?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>' : 
                                '<i class="fas fa-robot"></i>'}
                        </div>
                        <div class="chat-bubble chat-bubble-${sender}">
                            <div class="chat-content chat-markdown">${processedText}</div>
                            <div class="chat-time ${sender === 'ai' ? 'chat-time-ai' : ''}">${timeString}</div>
                        </div>
                    `;
                    
                    chatMessages.appendChild(messageElement);
                    
                    // Scroll to bottom
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
                
                // Function to process code blocks in messages
                function processCodeBlocks(text) {
                    // Replace code blocks with formatted HTML
                    return text.replace(/\`\`\`(\w+)?\n([\s\S]*?)\`\`\`/g, (match, language, code) => {
                        const lang = language || 'plaintext';
                        return `<div class="code-block">
                                    <div class="code-header">
                                        <span class="code-language">${lang}</span>
                                        <button class="code-copy" onclick="copyCode(this)">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                    <pre><code class="language-${lang}">${escapeHtml(code.trim())}</code></pre>
                                </div>`;
                    });
                }
                
                // Function to escape HTML special characters
                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
                
                // Function to copy code to clipboard
                window.copyCode = function(button) {
                    const codeBlock = button.closest('.code-block');
                    const code = codeBlock.querySelector('code').textContent;
                    
                    navigator.clipboard.writeText(code)
                        .then(() => {
                            // Change button icon temporarily
                            const icon = button.querySelector('i');
                            icon.classList.remove('fa-copy');
                            icon.classList.add('fa-check');
                            
                            setTimeout(() => {
                                icon.classList.remove('fa-check');
                                icon.classList.add('fa-copy');
                            }, 2000);
                        })
                        .catch(err => {
                            console.error('Failed to copy code: ', err);
                        });
                }
                
                // Generate session ID
                function generateSessionId() {
                    return 'session_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
                }
                
                // Add this function to the JavaScript section, right after the processCodeBlocks function
                function handleUserCodeInput() {
                    // Listen for triple backtick in user input
                    chatInput.addEventListener('input', function() {
                        const text = this.value;
                        if (text.includes('\`\`\`')) {
                            // If user is typing code, apply special styling
                            this.style.fontFamily = "'Fira Code', monospace";
                            this.style.backgroundColor = "#f8fafc";
                        } else {
                            // Reset styling for normal text
                            this.style.fontFamily = "";
                            this.style.backgroundColor = "";
                        }
                    });
                }
            }
        });

// Fungsi untuk memaksa refresh editor
function forceRefreshEditor() {
    if (editor) {
        editor.refresh();
        
        // Pastikan editor terlihat
        const editorElement = document.querySelector('.CodeMirror');
        if (editorElement) {
            editorElement.style.height = 'auto';
            editorElement.style.minHeight = '500px';
            editorElement.style.display = 'block';
            editorElement.style.visibility = 'visible';
            editorElement.style.opacity = '1';
        }
    }
}

// Panggil fungsi refresh saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
    forceRefreshEditor();
    
    // Refresh berkala selama 10 detik pertama
    let refreshCount = 0;
    const refreshInterval = setInterval(() => {
        forceRefreshEditor();
        refreshCount++;
        if (refreshCount >= 10) {
            clearInterval(refreshInterval);
        }
    }, 1000);
});

// Refresh saat ukuran window berubah
window.addEventListener('resize', forceRefreshEditor);

// Refresh saat tab menjadi aktif
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        forceRefreshEditor();
    }
});

// Tambahkan fungsi toggleFullscreen jika belum ada
function toggleFullscreen() {
    if (editorCard) {
        editorCard.classList.toggle('fullscreen');
        if (editorCard.classList.contains('fullscreen')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
        forceRefreshEditor();
    }
}
    </script>
</body>
</html>
<?php endif; ?>
