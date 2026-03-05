// ── Auto-dismiss alerts after 4s ──
document.querySelectorAll('.alert.alert-dismissible').forEach(el => {
  setTimeout(() => {
    const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
    bsAlert.close();
  }, 4000);
});

// ── Confirm before delete ──
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm || 'Are you sure?')) {
      e.preventDefault();
    }
  });
});

// ── Table search (client-side) ──
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
  searchInput.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ── Number formatting ──
function formatCurrency(num) {
  return new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2 }).format(num);
}

// ── Sales form: auto-calc total price ──
const sellUnitInput = document.getElementById('sellUnitInput');
const unitPriceHidden = document.getElementById('unitPriceHidden');
const totalDisplay = document.getElementById('totalDisplay');
if (sellUnitInput && unitPriceHidden && totalDisplay) {
  sellUnitInput.addEventListener('input', function() {
    const qty = parseFloat(this.value) || 0;
    const price = parseFloat(unitPriceHidden.value) || 0;
    totalDisplay.textContent = formatCurrency(qty * price);
  });
}

// ── Product select on sales page: populate hidden fields ──
const productSelect = document.getElementById('productSelect');
if (productSelect) {
  productSelect.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    if (!selected.value) return;
    document.getElementById('unitPriceHidden').value = selected.dataset.price || 0;
    document.getElementById('maxUnit').textContent = selected.dataset.unit || 0;
    document.getElementById('maxUnitInput').max = selected.dataset.unit || 0;
    document.getElementById('productDetails').classList.remove('d-none');
    document.getElementById('priceDisplay').textContent = formatCurrency(selected.dataset.price || 0);
    document.getElementById('stockDisplay').textContent = selected.dataset.unit || 0;
    if (sellUnitInput) { sellUnitInput.value = ''; totalDisplay.textContent = '0.00'; }
  });
}
