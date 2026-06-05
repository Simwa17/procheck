/**
 * ProCheck — Quote Builder JavaScript
 * Handles multi-step wizard, module selection, live pricing, and form submission.
 */

(function () {
  'use strict';

  // ── State ────────────────────────────────────────────────────────────────────
  let currentStep = 1;
  let selectedTier = 'mid';
  let marginPercent = 0;
  let selectedModules = {}; // { moduleId: { name, hours, complexity, description, moduleId } }

  // ── Step Navigation ──────────────────────────────────────────────────────────
  window.goStep = function (step) {
    if (step === 2) {
      const name = document.getElementById('project_name').value.trim();
      if (!name) {
        document.getElementById('project_name').classList.add('is-invalid');
        document.getElementById('project_name').focus();
        return;
      }
      document.getElementById('project_name').classList.remove('is-invalid');
    }

    if (step === 3) {
      if (Object.keys(selectedModules).length === 0) {
        alert('Please select at least one module before proceeding.');
        return;
      }
    }

    if (step === 4) {
      buildReview();
      prepareFormSubmission();
    }

    // Hide all panels
    document.querySelectorAll('.wizard-panel').forEach(p => p.classList.add('d-none'));
    document.getElementById('step-' + step).classList.remove('d-none');

    // Update step indicators
    document.querySelectorAll('.wizard-step').forEach(s => {
      const n = parseInt(s.dataset.step);
      s.classList.remove('active', 'done');
      if (n === step)   s.classList.add('active');
      if (n < step)     s.classList.add('done');
    });

    currentStep = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // ── Module Toggle ────────────────────────────────────────────────────────────
  window.toggleModule = function (card) {
    const item = card.closest('.module-item');
    const modId       = parseInt(item.dataset.id);
    const modName     = item.querySelector('.fw-medium')?.textContent.trim() || 'Module';
    const checkbox    = card.querySelector('.module-check');
    const complexRow  = card.querySelector('.complexity-row');
    const customRow   = card.querySelector('.custom-feature-row');
    const isCustom    = (modId === 30);

    if (selectedModules[modId]) {
      // Deselect
      delete selectedModules[modId];
      card.classList.remove('selected');
      checkbox.checked = false;
      if (complexRow)  complexRow.classList.add('d-none');
      if (customRow)   customRow.classList.add('d-none');
    } else {
      // Select
      card.classList.add('selected');
      checkbox.checked = true;
      if (complexRow)  complexRow.classList.remove('d-none');
      if (customRow && isCustom) customRow.classList.remove('d-none');

      const complexity = getSelectedComplexity(card, modId) || 'medium';
      const hours      = getHoursForComplexity(item, complexity);
      selectedModules[modId] = {
        moduleId:    modId,
        name:        isCustom ? (card.querySelector('.custom-name')?.value.trim() || 'Custom Feature') : modName,
        description: '',
        complexity:  complexity,
        hours:       hours,
      };
    }
    updateSummary();
  };

  // Listen for complexity radio changes
  document.addEventListener('change', function (e) {
    if (!e.target.classList.contains('complexity-radio')) return;
    const card    = e.target.closest('.module-card');
    const item    = card.closest('.module-item');
    const modId   = parseInt(item.dataset.id);
    if (!selectedModules[modId]) return;

    const complexity = e.target.value;
    const hours      = getHoursForComplexity(item, complexity);
    selectedModules[modId].complexity = complexity;
    selectedModules[modId].hours      = hours;
    updateSummary();
  });

  // Custom feature name & hours input
  document.addEventListener('input', function (e) {
    if (e.target.classList.contains('custom-name') || e.target.classList.contains('custom-hours')) {
      const card  = e.target.closest('.module-card');
      const item  = card.closest('.module-item');
      const modId = parseInt(item.dataset.id);
      if (!selectedModules[modId]) return;

      const nameInput  = card.querySelector('.custom-name');
      const hoursInput = card.querySelector('.custom-hours');
      selectedModules[modId].name  = nameInput?.value.trim() || 'Custom Feature';
      selectedModules[modId].hours = parseFloat(hoursInput?.value) || 0;
      updateSummary();
    }
  });

  function getSelectedComplexity(card, modId) {
    const checked = card.querySelector('.complexity-radio:checked');
    return checked ? checked.value : 'medium';
  }

  function getHoursForComplexity(item, complexity) {
    const map = { simple: 'simple', medium: 'medium', complex: 'complex' };
    const key = 'data-' + (map[complexity] || 'medium');
    return parseFloat(item.getAttribute(key)) || 0;
  }

  // ── Developer Tier Selection ─────────────────────────────────────────────────
  window.selectTier = function (card) {
    document.querySelectorAll('.tier-card').forEach(c => c.classList.remove('selected-tier'));
    card.classList.add('selected-tier');
    selectedTier = card.dataset.tier;
    document.getElementById('developer_tier').value = selectedTier;

    // Update summary tier label
    document.getElementById('sumTier').textContent = capitalize(selectedTier);
    const rate = RATES[selectedTier] || 0;
    document.getElementById('sumRate').textContent = 'MWK ' + fmt(rate) + '/hr';
    updateSummary();
  };

  // Pre-select "mid" tier card
  document.addEventListener('DOMContentLoaded', function () {
    const midCard = document.querySelector('.tier-card[data-tier="mid"]');
    if (midCard) selectTier(midCard);

    // Module search
    const search = document.getElementById('moduleSearch');
    if (search) {
      search.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('.module-item').forEach(item => {
          const name = item.dataset.name || '';
          item.style.display = (!q || name.includes(q)) ? '' : 'none';
        });
        // Hide empty categories
        document.querySelectorAll('.module-category').forEach(cat => {
          const visible = [...cat.querySelectorAll('.module-item')].some(i => i.style.display !== 'none');
          cat.style.display = visible ? '' : 'none';
        });
      });
    }
  });

  // ── Margin ───────────────────────────────────────────────────────────────────
  window.updateMargin = function (val) {
    marginPercent = parseFloat(val) || 0;
    document.getElementById('marginDisplay').textContent = marginPercent + '%';
    document.getElementById('sumMarginPct').textContent  = marginPercent;
    updateSummary();
  };

  // ── Live Summary ─────────────────────────────────────────────────────────────
  function updateSummary() {
    const modules   = Object.values(selectedModules);
    const totalHrs  = modules.reduce((s, m) => s + (parseFloat(m.hours) || 0), 0);
    const rate      = parseFloat(RATES[selectedTier]) || 0;
    const subtotal  = totalHrs * rate;
    const marginAmt = subtotal * (marginPercent / 100);
    const total     = subtotal + marginAmt;
    const totalUSD  = USD_RATE > 0 ? total / USD_RATE : 0;

    document.getElementById('sumModules').textContent   = modules.length;
    document.getElementById('sumHours').textContent     = totalHrs.toFixed(1) + ' hrs';
    document.getElementById('sumRate').textContent      = 'MWK ' + fmt(rate) + '/hr';
    document.getElementById('sumTier').textContent      = capitalize(selectedTier);
    document.getElementById('sumSubtotal').textContent  = 'MWK ' + fmt(subtotal);
    document.getElementById('sumMarginAmt').textContent = 'MWK ' + fmt(marginAmt);
    document.getElementById('sumTotalMWK').textContent  = 'MWK ' + fmt(total);
    document.getElementById('sumTotalUSD').textContent  = 'USD ' + fmtUSD(totalUSD);
    document.getElementById('sumMarginPct').textContent = marginPercent;
    document.getElementById('sumUSDRate').textContent   = fmt(USD_RATE);
  }

  // ── Step 4: Review ───────────────────────────────────────────────────────────
  function buildReview() {
    const modules   = Object.values(selectedModules);
    const rate      = parseFloat(RATES[selectedTier]) || 0;
    const totalHrs  = modules.reduce((s, m) => s + (parseFloat(m.hours) || 0), 0);
    const subtotal  = totalHrs * rate;
    const marginAmt = subtotal * (marginPercent / 100);
    const total     = subtotal + marginAmt;
    const totalUSD  = USD_RATE > 0 ? total / USD_RATE : 0;

    const projName  = document.getElementById('project_name')?.value || 'Untitled';

    let rows = modules.map(m => {
      const lineMWK = (parseFloat(m.hours) || 0) * rate;
      return `<tr>
        <td class="fw-medium">${escHtml(m.name)}</td>
        <td class="text-center"><span class="badge bg-${complexColor(m.complexity)}">${capitalize(m.complexity)}</span></td>
        <td class="text-end">${parseFloat(m.hours).toFixed(1)}</td>
        <td class="text-end">MWK ${fmt(rate)}</td>
        <td class="text-end fw-semibold">MWK ${fmt(lineMWK)}</td>
      </tr>`;
    }).join('');

    document.getElementById('reviewContent').innerHTML = `
      <div class="alert alert-info py-2 small mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Reviewing <strong>${escHtml(projName)}</strong> — ${modules.length} module(s) selected for a <strong>${capitalize(selectedTier)}</strong> developer
      </div>
      <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Module</th>
              <th class="text-center">Complexity</th>
              <th class="text-end">Hours</th>
              <th class="text-end">Rate/hr</th>
              <th class="text-end">Subtotal</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
          <tfoot class="table-light">
            <tr><td colspan="2" class="text-muted">Totals</td><td class="text-end fw-bold">${totalHrs.toFixed(1)}</td><td></td><td class="text-end fw-bold">MWK ${fmt(subtotal)}</td></tr>
            ${marginPercent > 0 ? `<tr><td colspan="4" class="text-end text-muted">Margin (${marginPercent}%)</td><td class="text-end">MWK ${fmt(marginAmt)}</td></tr>` : ''}
            <tr class="table-primary"><td colspan="4" class="text-end fw-bold">TOTAL (MWK)</td><td class="text-end fw-bold fs-6">MWK ${fmt(total)}</td></tr>
            <tr><td colspan="4" class="text-end text-muted small">≈ USD</td><td class="text-end text-muted small">USD ${fmtUSD(totalUSD)}</td></tr>
          </tfoot>
        </table>
      </div>`;
  }

  function prepareFormSubmission() {
    document.getElementById('items_json').value = JSON.stringify(
      Object.values(selectedModules).map(m => ({
        module_id:   m.moduleId,
        name:        m.name,
        description: m.description || '',
        complexity:  m.complexity,
        hours:       m.hours,
      }))
    );
  }

  // ── Helpers ──────────────────────────────────────────────────────────────────
  function fmt(n) {
    return Number(n).toLocaleString('en-MW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function fmtUSD(n) {
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function capitalize(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
  function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }
  function complexColor(c) {
    return { simple: 'info', medium: 'warning', complex: 'danger' }[c] || 'secondary';
  }

})();
