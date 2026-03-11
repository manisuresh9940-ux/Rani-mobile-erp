/**
 * Rani Mobiles ERP — Main JavaScript
 */
'use strict';

/* ─── Sidebar toggle (mobile) ─────────────────────────────── */
const sidebar      = document.getElementById('erpSidebar');
const overlay      = document.getElementById('sidebarOverlay');
const mobileToggle = document.getElementById('mobileSidebarToggle');

if (mobileToggle) {
  mobileToggle.addEventListener('click', () => {
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
  });
}
if (overlay) {
  overlay.addEventListener('click', () => {
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
  });
}

/* ─── Toast notification helper ──────────────────────────── */
let toastContainer = document.querySelector('.erp-toast-container');
if (!toastContainer) {
  toastContainer = document.createElement('div');
  toastContainer.className = 'erp-toast-container';
  document.body.appendChild(toastContainer);
}

function showToast(msg, type = 'success', duration = 4000) {
  const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
  const toast = document.createElement('div');
  toast.className = `erp-toast ${type}`;
  toast.innerHTML = `<span>${icons[type] || 'ℹ️'}</span> <span>${msg}</span>`;
  toastContainer.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(50px)';
    toast.style.transition = 'all 0.3s';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

/* ─── AJAX helper ─────────────────────────────────────────── */
async function erpFetch(url, data = null, method = 'GET') {
  const opts = { method, headers: {} };
  if (data) {
    opts.method = 'POST';
    if (data instanceof FormData) {
      opts.body = data;
    } else {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(data);
    }
  }
  const res = await fetch(url, opts);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

/* ─── Number formatting ───────────────────────────────────── */
function formatMoney(val) {
  return '₹' + parseFloat(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatNum(val) {
  return parseFloat(val || 0).toLocaleString('en-IN');
}

/* ─── Dashboard notifications ─────────────────────────────── */
async function loadNotifications() {
  const badge = document.getElementById('notifCount');
  const list  = document.getElementById('notifList');
  if (!badge || !list) return;

  try {
    const data = await erpFetch(BASE_URL + '/api/notifications.php');
    if (data.items && data.items.length) {
      badge.style.display = 'inline';
      badge.textContent   = data.items.length;
      list.innerHTML = data.items.map(n =>
        `<div class="px-3 py-2 border-bottom">
           <div class="fw-semibold small">${n.title}</div>
           <div class="text-muted" style="font-size:0.78rem">${n.message}</div>
         </div>`
      ).join('');
    } else {
      list.innerHTML = '<div class="px-3 py-2 text-muted small">No new alerts</div>';
    }
  } catch (e) {
    list.innerHTML = '<div class="px-3 py-2 text-muted small">Could not load alerts</div>';
  }
}

if (document.getElementById('notifDropdown')) {
  document.getElementById('notifDropdown')?.addEventListener('show.bs.dropdown', loadNotifications);
}

/* ─── POS utilities ───────────────────────────────────────── */
const POS = {
  cart: [],
  gstEnabled: false,

  addItem(product, qty = 1) {
    const idx = this.cart.findIndex(i => i.id === product.id && !product.imei);
    if (idx >= 0 && !product.imei) {
      this.cart[idx].qty += qty;
    } else {
      this.cart.push({ ...product, qty, discount: 0 });
    }
    this.render();
  },

  removeItem(index) {
    this.cart.splice(index, 1);
    this.render();
  },

  updateQty(index, qty) {
    if (qty < 1) return;
    this.cart[index].qty = qty;
    this.render();
  },

  updateDiscount(index, discount) {
    this.cart[index].discount = parseFloat(discount) || 0;
    this.render();
  },

  getSubtotal() {
    return this.cart.reduce((sum, item) => {
      const line = (item.sale_price * item.qty) - item.discount;
      return sum + line;
    }, 0);
  },

  getGST() {
    if (!this.gstEnabled) return 0;
    return this.cart.reduce((sum, item) => {
      const line = (item.sale_price * item.qty) - item.discount;
      return sum + line * (item.gst_percent / 100);
    }, 0);
  },

  getTotal() {
    return this.getSubtotal() + this.getGST();
  },

  render() {
    const tbody  = document.getElementById('posCartBody');
    const subtEl = document.getElementById('posSubtotal');
    const gstEl  = document.getElementById('posGST');
    const totEl  = document.getElementById('posTotal');
    if (!tbody) return;

    if (this.cart.length === 0) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">
        <i class="bi bi-cart-x fs-2"></i><br>Cart is empty</td></tr>`;
    } else {
      tbody.innerHTML = this.cart.map((item, i) => {
        const lineTotal = (item.sale_price * item.qty) - item.discount;
        return `<tr>
          <td>${i + 1}</td>
          <td>
            <div class="fw-semibold small">${item.name}</div>
            ${item.imei ? `<div class="text-muted" style="font-size:.75rem">IMEI: ${item.imei}</div>` : ''}
          </td>
          <td>${formatMoney(item.sale_price)}</td>
          <td>
            <input type="number" class="form-control form-control-sm" style="width:65px"
              value="${item.qty}" min="1"
              onchange="POS.updateQty(${i}, this.value)">
          </td>
          <td>
            <input type="number" class="form-control form-control-sm" style="width:75px"
              value="${item.discount}" min="0"
              onchange="POS.updateDiscount(${i}, this.value)">
          </td>
          <td class="fw-semibold">${formatMoney(lineTotal)}</td>
          <td>
            <button class="btn btn-sm btn-outline-danger" onclick="POS.removeItem(${i})">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>`;
      }).join('');
    }

    if (subtEl) subtEl.textContent = formatMoney(this.getSubtotal());
    if (gstEl)  gstEl.textContent  = formatMoney(this.getGST());
    if (totEl)  totEl.textContent  = formatMoney(this.getTotal());

    // Update payment total
    const payTotal = document.getElementById('payTotal');
    if (payTotal) payTotal.textContent = formatMoney(this.getTotal());
  },

  clearCart() {
    this.cart = [];
    this.render();
  }
};

/* ─── Camera Barcode Scanner (using ZXing) ────────────────── */
const Scanner = {
  stream: null,
  reader: null,

  async open(onResult) {
    const modal = new bootstrap.Modal(document.getElementById('scannerModal'));
    modal.show();

    const video = document.getElementById('scannerVideo');
    if (!video) return;

    try {
      const { BrowserMultiFormatReader } = ZXing;
      this.reader = new BrowserMultiFormatReader();
      this.stream = await this.reader.decodeFromVideoDevice(null, 'scannerVideo', (result, err) => {
        if (result) {
          modal.hide();
          this.stop();
          onResult(result.getText());
        }
      });
    } catch (e) {
      showToast('Camera access denied: ' + e.message, 'error');
    }
  },

  stop() {
    if (this.reader) { this.reader.reset(); this.reader = null; }
  }
};

/* ─── Utility: debounce ───────────────────────────────────── */
function debounce(fn, delay = 300) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

/* ─── Confirm dialog ──────────────────────────────────────── */
function confirmAction(msg, fn) {
  if (window.confirm(msg)) fn();
}

/* ─── BASE_URL global (injected by PHP) ────────────────────── */
const BASE_URL = window.BASE_URL || '';
