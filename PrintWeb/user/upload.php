<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$errors   = [];
$success  = false;
$fileInfo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $file = $_FILES['document'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed. Please try again.';
    } elseif (!isAllowedFileType($file['name'])) {
        $allowed = getSetting('allowed_file_types', 'pdf,doc,docx');
        $errors[] = "File type not allowed. Allowed types: {$allowed}";
    } elseif ($file['size'] > getMaxFileSizeBytes()) {
        $maxMb = getSetting('max_file_size_mb', '50');
        $errors[] = "File size exceeds the maximum limit of {$maxMb} MB.";
    } else {
        // Save file
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

        $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safeName  = 'doc_' . uniqid() . '.' . $ext;
        $destPath  = UPLOAD_DIR . $safeName;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            $pages = 1; // Default; real page count requires a library like FPDF/TCPDF

            // Try to count PDF pages
            if ($ext === 'pdf') {
                $content = file_get_contents($destPath);
                preg_match_all('/\/Page\b/i', $content, $matches);
                $pages = max(1, count($matches[0]));
            }

            // Store in session for next step
            $_SESSION['upload'] = [
                'original_name' => $file['name'],
                'saved_name'    => $safeName,
                'file_path'     => $destPath,
                'file_size'     => $file['size'],
                'file_ext'      => $ext,
                'total_pages'   => $pages,
                'uploaded_at'   => date('Y-m-d H:i:s'),
            ];

            $success = true;
            $fileInfo = $_SESSION['upload'];
        } else {
            $errors[] = 'Failed to save the uploaded file. Please try again.';
        }
    }
}

// If upload already done in session, show it
if (isset($_SESSION['upload']) && !isset($_POST['remove'])) {
    $fileInfo = $_SESSION['upload'];
    $success  = true;
}

// Handle remove
if (isset($_POST['remove'])) {
    if (isset($_SESSION['upload']['file_path']) && file_exists($_SESSION['upload']['file_path'])) {
        @unlink($_SESSION['upload']['file_path']);
    }
    unset($_SESSION['upload']);
    $fileInfo = null;
    $success  = false;
}

$pageTitle = 'Upload Document';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../includes/sidebar_user.php'; ?>

<div class="main-content">
    <div class="topbar">
        <button class="icon-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">Upload Document<small>Step 1 of 5</small></div>
        <div class="topbar-actions">
            <a href="<?= BASE_URL ?>/user/dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>

    <div class="page-content">
        <!-- Steps bar -->
        <div class="steps-bar">
            <?php
            $steps = ['Upload','Settings','Summary','Payment','Queue'];
            foreach ($steps as $i => $s) {
                $cls = $i === 0 ? 'active' : '';
                echo "<div class='step-item {$cls}'><div class='step-circle'>" . ($i+1) . "</div><div class='step-label'>{$s}</div></div>";
            }
            ?>
        </div>

        <div style="max-width:800px;margin:0 auto">
            <div class="page-header">
                <div>
                    <h1>Upload Your Document</h1>
                    <p>Upload the document you want to print. Supported formats: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, JPG, PNG</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger fade-in">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?= implode('<br>', array_map('h', $errors)) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success && $fileInfo): ?>
                <!-- Success State -->
                <div class="alert alert-success fade-in">
                    <i class="fas fa-check-circle"></i>
                    <div><strong>Status: Uploaded</strong> — Document uploaded successfully.</div>
                </div>

                <div class="card fade-in" style="margin-bottom:20px">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-file-alt"></i> Uploaded Document</div>
                        <form method="POST" style="display:inline">
                            <button type="submit" name="remove" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="file-preview-card" style="margin-bottom:16px">
                            <?php
                            $iconClass = match($fileInfo['file_ext']) {
                                'pdf'  => 'fa-file-pdf',
                                'doc','docx' => 'fa-file-word',
                                'xls','xlsx' => 'fa-file-excel',
                                'ppt','pptx' => 'fa-file-powerpoint',
                                'jpg','jpeg','png' => 'fa-file-image',
                                default => 'fa-file-alt',
                            };
                            ?>
                            <div class="file-icon"><i class="fas <?= $iconClass ?>"></i></div>
                            <div class="file-info">
                                <div class="file-name"><?= h($fileInfo['original_name']) ?></div>
                                <div class="file-meta">
                                    Size: <?= formatFileSize($fileInfo['file_size']) ?> &nbsp;|&nbsp;
                                    Pages: <?= $fileInfo['total_pages'] ?> &nbsp;|&nbsp;
                                    Type: <?= strtoupper($fileInfo['file_ext']) ?> &nbsp;|&nbsp;
                                    Uploaded: <?= formatDateTime($fileInfo['uploaded_at'], 'M d, Y h:i A') ?>
                                </div>
                            </div>
                            <span class="badge badge-success"><i class="fas fa-check"></i> Uploaded</span>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px">
                            <div style="background:var(--gray-50);padding:14px;border-radius:8px;text-align:center;border:1px solid var(--gray-200)">
                                <div style="font-size:1.3rem;font-weight:700;color:var(--primary)"><?= $fileInfo['total_pages'] ?></div>
                                <div style="font-size:.75rem;color:var(--gray-400)">Total Pages</div>
                            </div>
                            <div style="background:var(--gray-50);padding:14px;border-radius:8px;text-align:center;border:1px solid var(--gray-200)">
                                <div style="font-size:1.3rem;font-weight:700;color:var(--primary)"><?= formatFileSize($fileInfo['file_size']) ?></div>
                                <div style="font-size:.75rem;color:var(--gray-400)">File Size</div>
                            </div>
                            <div style="background:var(--gray-50);padding:14px;border-radius:8px;text-align:center;border:1px solid var(--gray-200)">
                                <div style="font-size:1.3rem;font-weight:700;color:var(--primary)"><?= strtoupper($fileInfo['file_ext']) ?></div>
                                <div style="font-size:.75rem;color:var(--gray-400)">Format</div>
                            </div>
                        </div>

                        <?php if ($fileInfo['file_ext'] === 'pdf'): ?>
                        <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;margin-bottom:16px">
                            <div style="padding:8px 12px;background:var(--gray-50);font-size:.75rem;font-weight:600;color:var(--gray-500);border-bottom:1px solid var(--gray-200)">
                                <i class="fas fa-eye"></i> Document Preview
                            </div>
                            <iframe src="<?= BASE_URL . '/uploads/' . h($fileInfo['saved_name']) ?>" class="doc-preview-frame"></iframe>
                        </div>
                        <?php endif; ?>

                        <div style="display:flex;justify-content:flex-end;gap:10px">
                            <a href="<?= BASE_URL ?>/user/upload.php" class="btn btn-secondary">
                                <i class="fas fa-sync"></i> Replace File
                            </a>
                            <a href="<?= BASE_URL ?>/user/print_settings.php" class="btn btn-primary">
                                <i class="fas fa-cog"></i> Configure Print Settings →
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Upload Form -->
                <div class="card fade-in">
                    <div class="card-body" style="padding:28px">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="upload-dropzone" id="dropzone" onclick="document.getElementById('fileInput').click()">
                                <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                <h3>Drag & drop your file here</h3>
                                <p>or click to browse files from your computer</p>
                                <div style="margin-top:14px">
                                    <span style="font-size:.75rem;color:var(--gray-400)">
                                        Supported: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT, JPG, PNG &nbsp;|&nbsp; Max: <?= getSetting('max_file_size_mb', '50') ?>MB
                                    </span>
                                </div>
                            </div>
                            <input type="file" id="fileInput" name="document" style="display:none"
                                   accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.jpg,.jpeg,.png">

                            <div id="filePreview" style="display:none;margin-top:16px"></div>

                            <div style="margin-top:20px;display:flex;justify-content:flex-end">
                                <button type="submit" id="uploadBtn" class="btn btn-primary" disabled>
                                    <i class="fas fa-upload"></i> Upload Document
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
const dropzone   = document.getElementById('dropzone');
const fileInput  = document.getElementById('fileInput');
const filePreview = document.getElementById('filePreview');
const uploadBtn  = document.getElementById('uploadBtn');

if (dropzone) {
    ['dragover','dragenter'].forEach(e => dropzone.addEventListener(e, ev => { ev.preventDefault(); dropzone.classList.add('drag-over'); }));
    ['dragleave','drop'].forEach(e => dropzone.addEventListener(e, ev => { dropzone.classList.remove('drag-over'); }));
    dropzone.addEventListener('drop', ev => {
        ev.preventDefault();
        if (ev.dataTransfer.files.length) {
            fileInput.files = ev.dataTransfer.files;
            showPreview(ev.dataTransfer.files[0]);
        }
    });

    fileInput.addEventListener('change', function() {
        if (this.files.length) showPreview(this.files[0]);
    });

    function showPreview(file) {
        const size = file.size > 1048576 ? (file.size/1048576).toFixed(2)+' MB' : (file.size/1024).toFixed(1)+' KB';
        filePreview.style.display = 'block';
        filePreview.innerHTML = `
            <div class="file-preview-card">
                <div class="file-icon"><i class="fas fa-file-alt"></i></div>
                <div class="file-info">
                    <div class="file-name">${file.name}</div>
                    <div class="file-meta">Size: ${size} | Type: ${file.name.split('.').pop().toUpperCase()}</div>
                </div>
                <span class="badge badge-info">Ready to upload</span>
            </div>`;
        uploadBtn.disabled = false;
        dropzone.style.display = 'none';
    }
}
</script>
