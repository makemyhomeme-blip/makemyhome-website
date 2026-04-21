/* ===================================================
   MAKE MY HOME – Cart Logic (localStorage)
   =================================================== */

const CART_KEY = 'mmh_cart';

function getCart() {
  try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; }
  catch(e) { return []; }
}

function saveCart(cart) {
  localStorage.setItem(CART_KEY, JSON.stringify(cart));
  updateCartBadge();
}

function addToCart(product, qty) {
  qty = parseInt(qty) || 1;
  const effectivePrice = product.discount > 0
    ? +(product.price * (1 - product.discount / 100)).toFixed(2)
    : +product.price;
  const cart = getCart();
  const existing = cart.find(i => i.id === product.id);
  if (existing) {
    existing.qty += qty;
  } else {
    cart.push({
      id: product.id,
      name: product.name,
      price: effectivePrice,
      originalPrice: +product.price,
      discount: product.discount || 0,
      image: product.image,
      unit: product.unit || 'kom'
    });
    cart[cart.length - 1].qty = qty;
  }
  saveCart(cart);
  showCartToast(product.name);
}

function addProductToCartById(id, qty) {
  if (typeof allProducts === 'undefined') return;
  const product = allProducts.find(p => p.id === +id);
  if (product) addToCart(product, qty || 1);
}

function removeFromCart(id) {
  saveCart(getCart().filter(i => i.id !== +id));
}

function updateCartQty(id, qty) {
  const cart = getCart();
  const item = cart.find(i => i.id === +id);
  if (item) {
    item.qty = Math.max(1, parseInt(qty) || 1);
    saveCart(cart);
  }
}

function clearCart() {
  localStorage.removeItem(CART_KEY);
  updateCartBadge();
}

function getCartCount() {
  return getCart().reduce((s, i) => s + (i.qty || 1), 0);
}

function getCartTotal() {
  return getCart().reduce((s, i) => s + i.price * (i.qty || 1), 0);
}

function updateCartBadge() {
  const count = getCartCount();
  document.querySelectorAll('.cart-badge').forEach(b => {
    b.textContent = count;
    b.style.display = count > 0 ? 'flex' : 'none';
  });
}

function showCartToast(name) {
  const old = document.getElementById('cart-toast');
  if (old) old.remove();

  if (!document.getElementById('cart-toast-anim')) {
    const s = document.createElement('style');
    s.id = 'cart-toast-anim';
    s.textContent = '@keyframes slideInToast{from{transform:translateY(80px);opacity:0}to{transform:translateY(0);opacity:1}}';
    document.head.appendChild(s);
  }

  const t = document.createElement('div');
  t.id = 'cart-toast';
  t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;background:#1a1814;border:1.5px solid rgba(201,168,108,0.65);color:#fff;padding:14px 16px;border-radius:14px;display:flex;align-items:center;gap:12px;box-shadow:0 8px 32px rgba(0,0,0,0.65);font-family:inherit;max-width:340px;width:calc(100vw - 48px);animation:slideInToast .3s ease;';
  t.innerHTML = `
    <i class="fas fa-check-circle" style="color:#c9a86c;font-size:22px;flex-shrink:0;"></i>
    <div style="flex:1;min-width:0;">
      <div style="font-weight:700;font-size:14px;margin-bottom:2px;">Dodato u korpu!</div>
      <div style="color:#aaa;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${name}</div>
    </div>
    <a href="korpa.html" style="flex-shrink:0;background:#c9a86c;color:#0a0a0a;padding:7px 14px;border-radius:8px;font-weight:700;font-size:12px;text-decoration:none;white-space:nowrap;">Korpa →</a>
  `;
  document.body.appendChild(t);
  setTimeout(() => { if (t.parentNode) t.remove(); }, 4500);
}

document.addEventListener('DOMContentLoaded', updateCartBadge);
