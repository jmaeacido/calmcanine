const params = new URLSearchParams(window.location.search);
const orderId = params.get("id");

const foundEl = document.querySelector("[data-order-found]");
const missingEl = document.querySelector("[data-order-missing]");
const detailsEl = document.querySelector("[data-order-details]");
const loadingEl = document.querySelector("[data-order-loading]");
const taxRow = document.querySelector("[data-summary-tax-row]");
const taxEl = document.querySelector("[data-summary-tax]");
const emailLeadEl = document.querySelector("[data-order-email-lead]");

const showMissing = ()=>{
  loadingEl.hidden = true;
  missingEl.hidden = false;
};

const renderOrder = (order)=>{
  loadingEl.hidden = true;
  foundEl.hidden = false;
  detailsEl.hidden = false;

  document.querySelector("[data-order-id]").textContent = order.id;
  document.querySelector("[data-order-email]").textContent = order.customer.email;

  if(emailLeadEl){
    emailLeadEl.textContent = order.email?.sent
      ? `We've received your order and sent a confirmation to ${order.customer.email}.`
      : `We've received your order. A confirmation email will be sent to ${order.customer.email} once mail delivery is connected.`;
  }

  document.querySelector("[data-order-lines]").innerHTML = order.items.map(item=>`
    <article class="cart-line cart-line--readonly">
      <img class="cart-line-image" src="${CartStore.resolveImage(item.image, 1)}" alt="" />
      <div class="cart-line-copy">
        <h3>${item.name}</h3>
        <p class="cart-line-meta">${CartStore.purchaseLabel(item.purchaseType)} · Qty ${item.quantity}</p>
      </div>
      <div class="cart-line-price">${CartStore.formatMoney(item.lineTotal)}</div>
    </article>
  `).join("");

  document.querySelector("[data-summary-subtotal]").textContent = CartStore.formatMoney(order.subtotal);
  document.querySelector("[data-summary-shipping]").textContent = order.shippingCost === 0
    ? "Free"
    : CartStore.formatMoney(order.shippingCost);

  if(taxRow && taxEl){
    taxRow.hidden = false;
    taxEl.textContent = CartStore.formatMoney(order.tax || 0);
  }

  document.querySelector("[data-summary-total]").textContent = CartStore.formatMoney(order.total);

  const s = order.shipping;
  document.querySelector("[data-shipping-address]").innerHTML = `
    ${s.name}<br />
    ${s.address1}<br />
    ${s.address2 ? `${s.address2}<br />` : ""}
    ${s.city}, ${s.state} ${s.zip}
  `;

  const paymentNote = order.payment?.provider === "stub"
    ? `${order.payment.brand} ending in ${order.payment.last4} (test mode — no charge yet)`
    : `${order.payment.brand} ending in ${order.payment.last4}`;

  document.querySelector("[data-payment-method]").textContent = paymentNote;
};

const loadOrder = async ()=>{
  if(!orderId){
    showMissing();
    return;
  }

  try{
    const result = await ApiClient.getOrder(orderId);
    renderOrder(result.order);
  }catch{
    showMissing();
  }
};

loadOrder();
