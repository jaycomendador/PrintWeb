<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

// Must have upload in session
if (!isset($_SESSION['upload'])) {
    redirect('/user/upload.php');
}

$upload = $_SESSION['upload'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $copies      = (int)($_POST['copies'] ?? 1);
    $printType   = $_POST['print_type'] ?? 'bw';
    $paperSize   = $_POST['paper_size'] ?? 'A4';
    $pagesToPrint = trim($_POST['pages_to_print'] ?? 'all');

    // Validation
    $maxCopies = (int)getSetting('max_copies', '50');
    if ($copies < 1 || $copies > $maxCopies) $errors[] = "Copies must be between 1 and {$maxCopies}.";
    if (!in_array($printType, ['bw','color'])) $errors[] = 'Invalid print type.';
    if (!in_array($paperSize, PAPER_SIZES)) $errors[] = 'Invalid paper size.';

    // Calculate selected pages
    $totalPages = $upload['total_pages'];
    $selectedPages = 0;
    if ($pagesToPrint === 'all') {
        $selectedPages = $totalPages;
    } else {
        // Count each requested page only once so overlapping ranges cannot inflate the charge.
        $selectedPageNumbers = [];
        if (!preg_match('/^\d+(?:\s*-\s*\d+)?(?:\s*,\s*\d+(?:\s*-\s*\d+)?)*$/', $pagesToPrint)) {
            $errors[] = 'Invalid page range. Use page numbers such as 1-3,5,7-9.';
        } else {
            foreach (explode(',', $pagesToPrint) as $part) {
                $range = array_map('intval', preg_split('/\s*-\s*/', trim($part)));
                $start = $range[0];
                $end = $range[1] ?? $start;
                if ($start < 1 || $end < $start || $end > $totalPages) {
                    $errors[] = "Page ranges must be between 1 and {$totalPages}, with the start page no greater than the end page.";
                    break;
                }
                for ($pageNumber = $start; $pageNumber <= $end; $pageNumber++) {
                    $selectedPageNumbers[$pageNumber] = true;
                }
            }
            $selectedPages = count($selectedPageNumbers);
        }
        if ($selectedPages < 1) $errors[] = 'Invalid page range. Please enter valid page numbers.';
    }

    if (empty($errors)) {
        $pricePerPage = getPricePerPage($printType, $paperSize);
        $totalCost    = calculateTotalCost($selectedPages, $copies, $printType, $paperSize);

        $_SESSION['print_settings'] = [
            'copies'        => $copies,
            'print_type'    => $printType,
            'paper_size'    => $paperSize,
            'pages_to_print' => $pagesToPrint,
            'selected_pages' => $selectedPages,
            'price_per_page' => $pricePerPage,
            'total_cost'    => $totalCost,
        ];

        redirect('/user/summary.php');
    }
}

// Pre-fill defaults
$settings = $_SESSION['print_settings'] ?? [
    'copies' => 1, 'print_type' => 'bw', 'paper_size' => 'A4',
    'pages_to_print' => 'all', 'selected_pages' => $upload['total_pages'],
    'price_per_page' => getPricePerPage('bw', 'A4'),
    'total_cost' => calculateTotalCost($upload['total_pages'], 1, 'bw', 'A4'),
];

$pageTitle = 'Print Settings';
include __DIR__ . '/../includes/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../includes/sidebar_user.php'; ?>

<div class="main-content">
    <div class="topbar">
        <button class="icon-btn" id="sidebarToggle"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">Print Settings<small>Step 2 of 5</small></div>
        <div class="topbar-actions">
            <a href="<?= BASE_URL ?>/user/upload.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="page-content">
        <div class="steps-bar">
            <?php
            $steps = ['Upload','Settings','Summary','Payment','Queue'];
            foreach ($steps as $i => $s) {
                $cls = $i === 0 ? 'completed' : ($i === 1 ? 'active' : '');
                echo "<div class='step-item {$cls}'><div class='step-circle'>" . ($i < 1 ? '<i class="fas fa-check" style="font-size:.7rem"></i>' : ($i+1)) . "</div><div class='step-label'>{$s}</div></div>";
            }
            ?>
        </div>

        <div style="max-width:900px;margin:0 auto">
            <div class="page-header">
                <div>
                    <h1>Print Settings</h1>
                    <p>Configure your printing preferences for <strong><?= h($upload['original_name']) ?></strong></p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><div><?= implode('<br>', array_map('h', $errors)) ?></div></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">
                <!-- Settings Form -->
                <form method="POST" id="settingsForm">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title"><i class="fas fa-sliders-h"></i> Printing Options</div>
                        </div>
                        <div class="card-body" style="display:flex;flex-direction:column;gap:20px">

                            <!-- Print Type -->
                            <div class="form-group" style="margin:0">
                                <label class="form-label">Print Type</label>
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                                    <label class="print-type-option" id="opt-bw">
                                        <input type="radio" name="print_type" value="bw" <?= $settings['print_type'] === 'bw' ? 'checked' : '' ?> style="display:none">
                                        <div class="option-card">
                                            <div class="option-icon" style="background:var(--gray-100);color:var(--gray-700)"><i class="fas fa-adjust"></i></div>
                                            <div>
                                                <div style="font-weight:600;font-size:.9rem">Black & White</div>
                                                <div style="font-size:.75rem;color:var(--gray-400)">Starting at <?= formatCurrency(getPricePerPage('bw', 'A4')) ?>/page</div>
                                            </div>
                                        </div>
                                    </label>
                                    <label class="print-type-option" id="opt-color">
                                        <input type="radio" name="print_type" value="color" <?= $settings['print_type'] === 'color' ? 'checked' : '' ?> style="display:none">
                                        <div class="option-card">
                                            <div class="option-icon" style="background:linear-gradient(135deg,#ff6b6b,#ffd93d,#6bcb77,#4d96ff);color:#fff"><i class="fas fa-palette"></i></div>
                                            <div>
                                                <div style="font-weight:600;font-size:.9rem">Color</div>
                                                <div style="font-size:.75rem;color:var(--gray-400)">Starting at <?= formatCurrency(getPricePerPage('color', 'A4')) ?>/page</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Paper Size -->
                            <div class="form-group" style="margin:0">
                                <label class="form-label" for="paper_size">Paper Size</label>
                                <select id="paper_size" name="paper_size" class="form-select">
                                    <?php foreach (PAPER_SIZES as $size): ?>
                                        <option value="<?= $size ?>" <?= $settings['paper_size'] === $size ? 'selected' : '' ?>><?= $size ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Copies -->
                            <div class="form-group" style="margin:0">
                                <label class="form-label" for="copies">Number of Copies</label>
                                <div style="display:flex;align-items:center;gap:10px">
                                    <button type="button" class="btn btn-secondary btn-icon" id="decCopies"><i class="fas fa-minus"></i></button>
                                    <input type="number" id="copies" name="copies" class="form-control" 
                                           value="<?= $settings['copies'] ?>" min="1" max="<?= getSetting('max_copies','50') ?>"
                                           style="width:80px;text-align:center">
                                    <button type="button" class="btn btn-secondary btn-icon" id="incCopies"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>

                            <!-- Pages to print -->
                            <div class="form-group" style="margin:0">
                                <label class="form-label">Pages to Print</label>
                                <div style="display:flex;gap:12px;margin-bottom:10px">
                                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                                        <input type="radio" name="page_range_type" value="all" id="rangeAll" <?= $settings['pages_to_print'] === 'all' ? 'checked' : '' ?>>
                                        <span style="font-size:.875rem">All Pages</span>
                                    </label>
                                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                                        <input type="radio" name="page_range_type" value="custom" id="rangeCustom" <?= $settings['pages_to_print'] !== 'all' ? 'checked' : '' ?>>
                                        <span style="font-size:.875rem">Custom Range</span>
                                    </label>
                                </div>
                                <div id="customRange" style="display:<?= $settings['pages_to_print'] !== 'all' ? 'block' : 'none' ?>">
                                    <input type="text" name="pages_to_print" id="pages_to_print" class="form-control"
                                           value="<?= h($settings['pages_to_print'] === 'all' ? '' : $settings['pages_to_print']) ?>"
                                           placeholder="e.g. 1-3, 5, 7-9  (Total: <?= $upload['total_pages'] ?> pages)">
                                    <div style="font-size:.72rem;color:var(--gray-400);margin-top:4px">
                                        Use commas and hyphens. Example: 1-3,5,8-10
                                    </div>
                                </div>
                                <input type="hidden" name="pages_to_print" id="pages_to_print_all" value="all" <?= $settings['pages_to_print'] !== 'all' ? 'disabled' : '' ?>>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:16px;display:flex;justify-content:flex-end">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-arrow-right"></i> Review Summary
                        </button>
                    </div>
                </form>

                <!-- Live Cost Summary -->
                <div class="card" id="costCard" style="position:sticky;top:80px">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-calculator"></i> Cost Summary</div>
                    </div>
                    <div class="card-body">
                        <div class="file-preview-card" style="margin-bottom:16px">
                            <div class="file-icon"><i class="fas fa-file-alt" style="font-size:1.5rem"></i></div>
                            <div class="file-info">
                                <div class="file-name" style="font-size:.8rem"><?= h($upload['original_name']) ?></div>
                                <div class="file-meta"><?= $upload['total_pages'] ?> pages • <?= formatFileSize($upload['file_size']) ?></div>
                            </div>
                        </div>

                        <div class="summary-row"><span class="label">Total Pages</span><span class="value" id="summPages"><?= $upload['total_pages'] ?></span></div>
                        <div class="summary-row"><span class="label">Pages to Print</span><span class="value" id="summSelectedPages"><?= $settings['selected_pages'] ?></span></div>
                        <div class="summary-row"><span class="label">Copies</span><span class="value" id="summCopies">1</span></div>
                        <div class="summary-row"><span class="label">Print Type</span><span class="value" id="summType">Black &amp; White</span></div>
                        <div class="summary-row"><span class="label">Paper Size</span><span class="value" id="summPaper">A4</span></div>
                        <div class="summary-row"><span class="label">Price per Page</span><span class="value" id="summPrice"><?= formatCurrency($settings['price_per_page']) ?></span></div>
                        <div class="summary-row total">
                            <span class="label">Total Cost</span>
                            <span class="value" id="summTotal"><?= formatCurrency($settings['total_cost']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<style>
.option-card {
    display: flex; align-items: center; gap: 12px;
    padding: 14px; border: 2px solid var(--gray-200);
    border-radius: 10px; cursor: pointer;
    transition: var(--transition);
}
.option-card:hover { border-color: var(--primary); }
.print-type-option input:checked ~ .option-card {
    border-color: var(--primary);
    background: var(--primary-subtle);
}
.option-icon { width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script>
const prices = <?= json_encode([
    'bw'    => ['A4' => getPricePerPage('bw','A4'), 'Letter' => getPricePerPage('bw','Letter'), 'Legal' => getPricePerPage('bw','Legal')],
    'color' => ['A4' => getPricePerPage('color','A4'), 'Letter' => getPricePerPage('color','Letter'), 'Legal' => getPricePerPage('color','Legal')],
]) ?>;
const totalFilePages = <?= $upload['total_pages'] ?>;
const symbol = '<?= getSetting('currency_symbol','₱') ?>';

function fmt(n) { return symbol + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }

function updateSummary() {
    const copies = parseInt(document.getElementById('copies').value) || 1;
    const printType = document.querySelector('input[name="print_type"]:checked')?.value || 'bw';
    const paperSize = document.getElementById('paper_size').value;
    const allPages  = document.getElementById('rangeAll').checked;

    let selectedPages = totalFilePages;
    if (!allPages) {
        const rangeStr = document.getElementById('pages_to_print')?.value || '';
        selectedPages = parsePageRange(rangeStr, totalFilePages);
    }

    const pricePerPage = prices[printType]?.[paperSize] || 2.00;
    const total = pricePerPage * selectedPages * copies;

    document.getElementById('summCopies').textContent = copies;
    document.getElementById('summType').textContent = printType === 'color' ? 'Color' : 'Black & White';
    document.getElementById('summPaper').textContent = paperSize;
    document.getElementById('summSelectedPages').textContent = selectedPages;
    document.getElementById('summPrice').textContent = fmt(pricePerPage);
    document.getElementById('summTotal').textContent = fmt(total);
}

function parsePageRange(str, max) {
    let count = 0;
    const parts = str.split(',');
    parts.forEach(p => {
        p = p.trim();
        if (p.includes('-')) {
            const [a, b] = p.split('-').map(Number);
            const start = Math.max(1, a || 1);
            const end = Math.min(max, b || max);
            if (start <= end) count += end - start + 1;
        } else if (!isNaN(parseInt(p))) {
            const n = parseInt(p);
            if (n >= 1 && n <= max) count++;
        }
    });
    return count || max;
}

// Radio buttons
document.querySelectorAll('input[name="print_type"]').forEach(r => r.addEventListener('change', updateSummary));
document.getElementById('paper_size').addEventListener('change', updateSummary);
document.getElementById('copies').addEventListener('input', updateSummary);

// Copies +/-
document.getElementById('decCopies').addEventListener('click', () => {
    const el = document.getElementById('copies');
    if (parseInt(el.value) > 1) { el.value--; updateSummary(); }
});
document.getElementById('incCopies').addEventListener('click', () => {
    const el = document.getElementById('copies');
    el.value++; updateSummary();
});

// Page range toggle
document.getElementById('rangeAll').addEventListener('change', function() {
    document.getElementById('customRange').style.display = 'none';
    document.getElementById('pages_to_print_all').disabled = false;
    if (document.getElementById('pages_to_print')) document.getElementById('pages_to_print').disabled = true;
    updateSummary();
});
document.getElementById('rangeCustom').addEventListener('change', function() {
    document.getElementById('customRange').style.display = 'block';
    document.getElementById('pages_to_print_all').disabled = true;
    if (document.getElementById('pages_to_print')) document.getElementById('pages_to_print').disabled = false;
    updateSummary();
});
if (document.getElementById('pages_to_print'))
    document.getElementById('pages_to_print').addEventListener('input', updateSummary);

updateSummary();
</script>
