<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $project_name    = trim($_POST['project_name'] ?? '');
    $project_type_id = (int)($_POST['project_type_id'] ?? 0) ?: null;
    $client_id       = (int)($_POST['client_id'] ?? 0) ?: null;
    $dev_tier        = $_POST['developer_tier'] ?? 'mid';
    $margin          = max(0, min(200, (float)($_POST['margin_percent'] ?? 0)));
    $notes           = trim($_POST['notes'] ?? '');
    $valid_until     = $_POST['valid_until'] ?? null;
    $items_json      = $_POST['items_json'] ?? '[]';

    if (!$project_name) {
        $_SESSION['qb_error'] = 'Project name is required.';
        header('Location: ' . APP_URL . '/quotes/create.php');
        exit;
    }

    $items = json_decode($items_json, true);
    if (!is_array($items) || empty($items)) {
        $_SESSION['qb_error'] = 'Please select at least one module.';
        header('Location: ' . APP_URL . '/quotes/create.php');
        exit;
    }

    $hourly_rate = get_rate_for_tier($dev_tier);
    $usd_rate    = (float)setting('usd_mwk_rate', '1800');
    $total_hours = 0;
    $subtotal    = 0;

    $line_items = [];
    foreach ($items as $item) {
        $hours = max(0, (float)($item['hours'] ?? 0));
        $line_mwk = $hours * $hourly_rate;
        $total_hours += $hours;
        $subtotal    += $line_mwk;
        $line_items[] = [
            'module_id'   => (int)($item['module_id'] ?? 0) ?: null,
            'module_name' => substr(trim($item['name'] ?? ''), 0, 150),
            'description' => substr(trim($item['description'] ?? ''), 0, 500),
            'complexity'  => in_array($item['complexity'] ?? '', ['simple','medium','complex']) ? $item['complexity'] : 'medium',
            'hours'       => $hours,
            'rate_mwk'    => $hourly_rate,
            'total_mwk'   => $line_mwk,
        ];
    }

    $total_mwk = $subtotal * (1 + $margin / 100);
    $total_usd = $usd_rate > 0 ? $total_mwk / $usd_rate : 0;
    $quote_num = next_quote_number();

    $pdo = db();
    $ins = $pdo->prepare('
        INSERT INTO quotes (user_id, client_id, quote_number, project_name, project_type_id,
            developer_tier, total_hours, subtotal_mwk, margin_percent, total_mwk, usd_rate,
            total_usd, notes, valid_until)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ');
    $ins->execute([
        $user['id'], $client_id, $quote_num, $project_name, $project_type_id,
        $dev_tier, $total_hours, $subtotal, $margin, $total_mwk, $usd_rate,
        $total_usd, $notes ?: null, $valid_until ?: null,
    ]);
    $quote_id = (int)$pdo->lastInsertId();

    $ins_item = $pdo->prepare('
        INSERT INTO quote_items (quote_id, module_id, module_name, description, complexity, hours, rate_mwk, total_mwk)
        VALUES (?,?,?,?,?,?,?,?)
    ');
    foreach ($line_items as $li) {
        $ins_item->execute([
            $quote_id, $li['module_id'], $li['module_name'], $li['description'],
            $li['complexity'], $li['hours'], $li['rate_mwk'], $li['total_mwk'],
        ]);
    }

    flash_set('success', "Quote {$quote_num} created successfully!");
    header('Location: ' . APP_URL . '/quotes/view.php?id=' . $quote_id);
    exit;
}

$page_title    = 'New Quote';
$project_types = get_project_types();
$clients       = get_clients($user['id']);
$modules_grouped = get_modules_with_categories();
$dev_rates     = get_developer_rates();
$usd_rate      = (float)setting('usd_mwk_rate', '1800');
$qb_error      = $_SESSION['qb_error'] ?? null;
unset($_SESSION['qb_error']);

// Encode modules as JSON for JS
$modules_json  = json_encode($modules_grouped, JSON_HEX_TAG);
$rates_json    = json_encode(array_column($dev_rates, 'hourly_rate_mwk', 'tier'), JSON_HEX_TAG);

$extra_head = '<link rel="stylesheet" href="' . APP_URL . '/assets/css/style.css">';
$extra_scripts = '<script src="' . APP_URL . '/assets/js/quote-builder.js"></script>';

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header mb-4">
  <div class="d-flex align-items-center justify-content-between">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-plus-circle me-2 text-primary"></i>New Quote</h4>
      <p class="text-muted mb-0">Build a project cost estimate step by step</p>
    </div>
    <a href="<?= APP_URL ?>/quotes/index.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
</div>

<?php if ($qb_error): ?>
<div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i><?= h($qb_error) ?></div>
<?php endif; ?>

<!-- Step Indicator -->
<div class="wizard-steps mb-4">
  <div class="wizard-step active" data-step="1">
    <div class="step-num">1</div><div class="step-label">Project Info</div>
  </div>
  <div class="wizard-step" data-step="2">
    <div class="step-num">2</div><div class="step-label">Select Modules</div>
  </div>
  <div class="wizard-step" data-step="3">
    <div class="step-num">3</div><div class="step-label">Rates & Margin</div>
  </div>
  <div class="wizard-step" data-step="4">
    <div class="step-num">4</div><div class="step-label">Review & Save</div>
  </div>
</div>

<form id="quoteForm" method="POST" action="<?= APP_URL ?>/quotes/create.php">
  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
  <input type="hidden" name="items_json" id="items_json" value="[]">

  <div class="row g-3">
    <!-- LEFT: Steps -->
    <div class="col-lg-8">

      <!-- Step 1: Project Info -->
      <div class="wizard-panel card border-0 shadow-sm" id="step-1">
        <div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-info-circle me-2 text-primary"></i>Project Information</h6></div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label fw-medium">Project Name <span class="text-danger">*</span></label>
            <input type="text" name="project_name" id="project_name" class="form-control" placeholder="e.g. Malawi Hospital Management System" required>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-medium">Project Type</label>
              <select name="project_type_id" id="project_type_id" class="form-select">
                <option value="">— Select type —</option>
                <?php foreach ($project_types as $pt): ?>
                <option value="<?= $pt['id'] ?>"><?= h($pt['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-medium">Client</label>
              <select name="client_id" class="form-select">
                <option value="">— No client —</option>
                <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>"><?= h($c['name']) ?><?= $c['company'] ? ' (' . h($c['company']) . ')' : '' ?></option>
                <?php endforeach; ?>
              </select>
              <a href="<?= APP_URL ?>/clients/create.php" class="small text-primary" target="_blank">
                <i class="bi bi-plus me-1"></i>Add new client
              </a>
            </div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <label class="form-label fw-medium">Valid Until</label>
              <input type="date" name="valid_until" class="form-control"
                     min="<?= date('Y-m-d') ?>"
                     value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label fw-medium">Notes / Description</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Any additional notes for the client..."></textarea>
          </div>
          <div class="d-flex justify-content-end mt-3">
            <button type="button" class="btn btn-primary" onclick="goStep(2)">
              Next: Select Modules <i class="bi bi-arrow-right ms-1"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 2: Module Selection -->
      <div class="wizard-panel card border-0 shadow-sm d-none" id="step-2">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
          <h6 class="fw-semibold mb-0"><i class="bi bi-puzzle me-2 text-primary"></i>Select Project Modules</h6>
          <div class="input-group" style="max-width:240px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="moduleSearch" class="form-control form-control-sm" placeholder="Search modules...">
          </div>
        </div>
        <div class="card-body">
          <p class="text-muted small">Select features for your project. Set the complexity (Simple / Medium / Complex) for each module — this adjusts the estimated hours automatically.</p>

          <div id="modulesContainer">
            <?php foreach ($modules_grouped as $category => $mods): ?>
            <div class="module-category mb-3">
              <h6 class="text-uppercase small text-muted fw-semibold mb-2 border-bottom pb-1"><?= h($category) ?></h6>
              <div class="row g-2">
                <?php foreach ($mods as $mod): ?>
                <div class="col-md-6 module-item"
                     data-name="<?= strtolower(h($mod['name'])) ?>"
                     data-id="<?= $mod['id'] ?>"
                     data-simple="<?= $mod['simple_hours'] ?>"
                     data-medium="<?= $mod['medium_hours'] ?>"
                     data-complex="<?= $mod['complex_hours'] ?>">
                  <div class="module-card card border <?= $mod['id'] == 30 ? 'border-warning' : '' ?>" onclick="toggleModule(this)">
                    <div class="card-body py-2 px-3">
                      <div class="d-flex align-items-start gap-2">
                        <input type="checkbox" class="form-check-input mt-1 module-check flex-shrink-0" tabindex="-1">
                        <div class="flex-grow-1">
                          <div class="fw-medium small"><?= h($mod['name']) ?></div>
                          <?php if ($mod['description']): ?>
                          <div class="text-muted" style="font-size:.75rem"><?= h($mod['description']) ?></div>
                          <?php endif; ?>
                          <div class="hours-display mt-1" style="font-size:.75rem">
                            Simple: <strong><?= $mod['simple_hours'] ?>h</strong> &bull;
                            Medium: <strong><?= $mod['medium_hours'] ?>h</strong> &bull;
                            Complex: <strong><?= $mod['complex_hours'] ?>h</strong>
                          </div>
                        </div>
                      </div>
                      <div class="complexity-row mt-2 d-none">
                        <div class="btn-group btn-group-sm w-100" role="group">
                          <input type="radio" class="btn-check complexity-radio" name="cx_<?= $mod['id'] ?>" value="simple" id="cx_s_<?= $mod['id'] ?>">
                          <label class="btn btn-outline-info" for="cx_s_<?= $mod['id'] ?>">Simple (<?= $mod['simple_hours'] ?>h)</label>
                          <input type="radio" class="btn-check complexity-radio" name="cx_<?= $mod['id'] ?>" value="medium" id="cx_m_<?= $mod['id'] ?>" checked>
                          <label class="btn btn-outline-warning" for="cx_m_<?= $mod['id'] ?>">Medium (<?= $mod['medium_hours'] ?>h)</label>
                          <input type="radio" class="btn-check complexity-radio" name="cx_<?= $mod['id'] ?>" value="complex" id="cx_c_<?= $mod['id'] ?>">
                          <label class="btn btn-outline-danger" for="cx_c_<?= $mod['id'] ?>">Complex (<?= $mod['complex_hours'] ?>h)</label>
                        </div>
                      </div>
                      <!-- Custom feature fields -->
                      <?php if ($mod['id'] == 30): ?>
                      <div class="custom-feature-row mt-2 d-none">
                        <input type="text" class="form-control form-control-sm mb-1 custom-name"
                               placeholder="Feature name" onclick="event.stopPropagation()">
                        <input type="number" class="form-control form-control-sm custom-hours"
                               placeholder="Estimated hours" min="1" onclick="event.stopPropagation()">
                      </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn-outline-secondary" onclick="goStep(1)">
              <i class="bi bi-arrow-left me-1"></i>Back
            </button>
            <button type="button" class="btn btn-primary" onclick="goStep(3)" id="nextToStep3">
              Next: Rates & Margin <i class="bi bi-arrow-right ms-1"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 3: Developer Tier & Margin -->
      <div class="wizard-panel card border-0 shadow-sm d-none" id="step-3">
        <div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-person-badge me-2 text-primary"></i>Developer Rate & Profit Margin</h6></div>
        <div class="card-body">
          <div class="mb-4">
            <label class="form-label fw-medium mb-3">Developer Tier</label>
            <div class="row g-3" id="tierCards">
              <?php foreach ($dev_rates as $rate): ?>
              <div class="col-md-4">
                <div class="tier-card card border text-center" data-tier="<?= $rate['tier'] ?>"
                     data-rate="<?= $rate['hourly_rate_mwk'] ?>" onclick="selectTier(this)">
                  <div class="card-body py-3">
                    <?= tier_badge($rate['tier']) ?>
                    <div class="fs-5 fw-bold mt-2"><?= format_mwk($rate['hourly_rate_mwk']) ?><span class="fs-6 fw-normal text-muted">/hr</span></div>
                    <div class="text-muted small"><?= h($rate['description'] ?? '') ?></div>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="developer_tier" id="developer_tier" value="mid">
          </div>

          <div class="mb-3">
            <label class="form-label fw-medium">
              Profit / Business Margin: <strong id="marginDisplay">0%</strong>
            </label>
            <input type="range" class="form-range" name="margin_percent" id="margin_range"
                   min="0" max="100" step="5" value="0" oninput="updateMargin(this.value)">
            <div class="d-flex justify-content-between text-muted small">
              <span>0% (cost)</span><span>25% typical</span><span>50%</span><span>100%</span>
            </div>
          </div>

          <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn-outline-secondary" onclick="goStep(2)">
              <i class="bi bi-arrow-left me-1"></i>Back
            </button>
            <button type="button" class="btn btn-primary" onclick="goStep(4)">
              Review Quote <i class="bi bi-arrow-right ms-1"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Step 4: Review -->
      <div class="wizard-panel card border-0 shadow-sm d-none" id="step-4">
        <div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-clipboard-check me-2 text-primary"></i>Review Quote</h6></div>
        <div class="card-body">
          <div id="reviewContent"><!-- populated by JS --></div>
          <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn btn-outline-secondary" onclick="goStep(3)">
              <i class="bi bi-arrow-left me-1"></i>Back
            </button>
            <button type="submit" class="btn btn-success btn-lg px-5" id="saveBtn">
              <i class="bi bi-save me-2"></i>Save Quote
            </button>
          </div>
        </div>
      </div>

    </div><!-- /col -->

    <!-- RIGHT: Live Summary -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm sticky-top" style="top:16px">
        <div class="card-header bg-primary text-white border-0">
          <h6 class="mb-0"><i class="bi bi-calculator me-2"></i>Live Estimate</h6>
        </div>
        <div class="card-body">
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Modules selected:</span>
            <strong id="sumModules">0</strong>
          </div>
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Total hours:</span>
            <strong id="sumHours">0 hrs</strong>
          </div>
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Rate (<span id="sumTier">Mid</span>):</span>
            <strong id="sumRate">MWK 0/hr</strong>
          </div>
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Subtotal:</span>
            <strong id="sumSubtotal">MWK 0</strong>
          </div>
          <div class="mb-2 d-flex justify-content-between">
            <span class="text-muted">Margin (<span id="sumMarginPct">0</span>%):</span>
            <strong id="sumMarginAmt">MWK 0</strong>
          </div>
          <hr>
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="fw-semibold fs-6">Total (MWK):</span>
            <span class="fw-bold fs-5 text-primary" id="sumTotalMWK">MWK 0</span>
          </div>
          <div class="d-flex justify-content-between align-items-center text-muted small">
            <span>≈ USD (@ <span id="sumUSDRate"><?= number_format($usd_rate, 0) ?></span>):</span>
            <span id="sumTotalUSD">USD 0</span>
          </div>
        </div>
        <div class="card-footer bg-white text-muted small border-0">
          <i class="bi bi-info-circle me-1"></i>
          USD/MWK rate configured in Admin → Settings
        </div>
      </div>
    </div>

  </div><!-- /row -->
</form>

<script>
const USD_RATE = <?= $usd_rate ?>;
const RATES    = <?= $rates_json ?>;
const APP_URL  = '<?= APP_URL ?>';
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
