const linesEl = document.querySelector("[data-cart-lines]");
const emptyEl = document.querySelector("[data-cart-empty]");
const checkoutBtn = document.querySelector("[data-checkout-btn]");
const subtotalEl = document.querySelector("[data-summary-subtotal]");
const shippingEl = document.querySelector("[data-summary-shipping]");
const totalEl = document.querySelector("[data-summary-total]");
const shippingNoteEl = document.querySelector("[data-shipping-note]");

const render = ()=>{
  const items = CartStore.getItems();
  const hasItems = items.length > 0;

  emptyEl.hidden = hasItems;
  checkoutBtn.hidden = !hasItems;
  linesEl.hidden = !hasItems;

  if(!hasItems){
    linesEl.innerHTML = "";
  }else{
    linesEl.innerHTML = items.map(item=>`
      <article class="cart-line" data-line="${item.productId}:${item.purchaseType}">
        <img class="cart-line-image" src="${CartStore.resolveImage(item.image, 1)}" alt="" />
        <div class="cart-line-copy">
          <h3>${item.name}</h3>
          <p class="cart-line-meta">${CartStore.purchaseLabel(item.purchaseType)}${item.purchaseType === "subscribe" ? " · Every month" : ""}</p>
          <p class="cart-line-sku">SKU ${item.sku}</p>
        </div>
        <div class="cart-line-qty">
          <button type="button" class="quantity-btn" data-qty-minus aria-label="Decrease quantity">−</button>
          <span class="cart-line-qty-value">${item.quantity}</span>
          <button type="button" class="quantity-btn" data-qty-plus aria-label="Increase quantity">+</button>
        </div>
        <div class="cart-line-price">${CartStore.formatMoney(CartStore.getLineTotal(item))}</div>
        <button type="button" class="cart-line-remove" data-remove aria-label="Remove item">×</button>
      </article>
    `).join("");

    linesEl.querySelectorAll(".cart-line").forEach(row=>{
      const key = row.dataset.line;
      const [productId, purchaseType] = key.split(":");
      row.querySelector("[data-qty-minus]")?.addEventListener("click", ()=>{
        const item = CartStore.getItems().find(i=>i.productId === productId && i.purchaseType === purchaseType);
        if(item) CartStore.updateQuantity(productId, purchaseType, item.quantity - 1);
        render();
      });
      row.querySelector("[data-qty-plus]")?.addEventListener("click", ()=>{
        const item = CartStore.getItems().find(i=>i.productId === productId && i.purchaseType === purchaseType);
        if(item) CartStore.updateQuantity(productId, purchaseType, item.quantity + 1);
        render();
      });
      row.querySelector("[data-remove]")?.addEventListener("click", ()=>{
        CartStore.removeItem(productId, purchaseType);
        render();
      });
    });
  }

  subtotalEl.textContent = CartStore.formatMoney(CartStore.getSubtotal());
  const shipping = CartStore.getShipping();
  shippingEl.textContent = shipping === 0 && CartStore.getSubtotal() > 0
    ? "Free"
    : CartStore.formatMoney(shipping);
  totalEl.textContent = CartStore.formatMoney(CartStore.getTotal());

  if(CartStore.getSubtotal() > 0 && CartStore.getSubtotal() < CartStore.FREE_SHIPPING_MIN){
    const remaining = CartStore.FREE_SHIPPING_MIN - CartStore.getSubtotal();
    shippingNoteEl.textContent = `Add ${CartStore.formatMoney(remaining)} more for free shipping.`;
  }else if(shipping === 0 && CartStore.getSubtotal() >= CartStore.FREE_SHIPPING_MIN){
    shippingNoteEl.textContent = "You've unlocked free shipping.";
  }else{
    shippingNoteEl.textContent = "";
  }
};

window.addEventListener("cart:updated", render);
render();
