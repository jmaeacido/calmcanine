const US_STATES = [
  ["AL", "Alabama"], ["AK", "Alaska"], ["AZ", "Arizona"], ["AR", "Arkansas"], ["CA", "California"],
  ["CO", "Colorado"], ["CT", "Connecticut"], ["DE", "Delaware"], ["FL", "Florida"], ["GA", "Georgia"],
  ["HI", "Hawaii"], ["ID", "Idaho"], ["IL", "Illinois"], ["IN", "Indiana"], ["IA", "Iowa"],
  ["KS", "Kansas"], ["KY", "Kentucky"], ["LA", "Louisiana"], ["ME", "Maine"], ["MD", "Maryland"],
  ["MA", "Massachusetts"], ["MI", "Michigan"], ["MN", "Minnesota"], ["MS", "Mississippi"], ["MO", "Missouri"],
  ["MT", "Montana"], ["NE", "Nebraska"], ["NV", "Nevada"], ["NH", "New Hampshire"], ["NJ", "New Jersey"],
  ["NM", "New Mexico"], ["NY", "New York"], ["NC", "North Carolina"], ["ND", "North Dakota"], ["OH", "Ohio"],
  ["OK", "Oklahoma"], ["OR", "Oregon"], ["PA", "Pennsylvania"], ["RI", "Rhode Island"], ["SC", "South Carolina"],
  ["SD", "South Dakota"], ["TN", "Tennessee"], ["TX", "Texas"], ["UT", "Utah"], ["VT", "Vermont"],
  ["VA", "Virginia"], ["WA", "Washington"], ["WV", "West Virginia"], ["WI", "Wisconsin"], ["WY", "Wyoming"]
];

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE_RE = /^[\d\s().+-]{7,20}$/;
const ZIP_RE = /^\d{5}(-\d{4})?$/;

const form = document.querySelector("[data-checkout-form]");
const emptyEl = document.querySelector("[data-checkout-empty]");
const itemsEl = document.querySelector("[data-checkout-items]");
const stateSelect = document.querySelector("[data-state-select]");
const stateError = document.querySelector("[data-state-error]");
const formError = document.querySelector("[data-form-error]");
const submitBtn = document.querySelector("[data-place-order]");
const taxRow = document.querySelector("[data-summary-tax-row]");
const taxEl = document.querySelector("[data-summary-tax]");

let quoteRequestId = 0;
let signedInUser = null;

const fillStates = ()=>{
  if(!stateSelect) return;
  US_STATES.forEach(([code, name])=>{
    const option = document.createElement("option");
    option.value = code;
    option.textContent = name;
    if(CartStore.RESTRICTED_STATES.includes(code)){
      option.disabled = true;
      option.textContent = `${name} (not eligible)`;
    }
    stateSelect.appendChild(option);
  });
};

const renderSummary = (quote = null)=>{
  const items = CartStore.getItems();
  const hasItems = items.length > 0;

  emptyEl.hidden = hasItems;
  form.hidden = !hasItems;

  if(!hasItems) return;

  itemsEl.innerHTML = items.map(item=>`
    <div class="checkout-summary-line">
      <img src="${CartStore.resolveImage(item.image, 1)}" alt="" />
      <div>
        <strong>${item.name}</strong>
        <span>${CartStore.purchaseLabel(item.purchaseType)} · Qty ${item.quantity}</span>
      </div>
      <span>${CartStore.formatMoney(CartStore.getLineTotal(item))}</span>
    </div>
  `).join("");

  const subtotal = quote?.subtotal ?? CartStore.getSubtotal();
  const shippingCost = quote?.shippingCost ?? CartStore.getShipping();
  const tax = quote?.tax ?? 0;
  const hasState = Boolean(stateSelect?.value);
  const total = quote?.total ?? (subtotal + shippingCost + (hasState ? tax : 0));

  document.querySelector("[data-summary-subtotal]").textContent = CartStore.formatMoney(subtotal);
  document.querySelector("[data-summary-shipping]").textContent = shippingCost === 0
    ? "Free"
    : CartStore.formatMoney(shippingCost);

  if(taxRow && taxEl){
    taxRow.hidden = !hasState;
    taxEl.textContent = hasState ? CartStore.formatMoney(tax) : "—";
  }

  document.querySelector("[data-summary-total]").textContent = CartStore.formatMoney(
    hasState ? total : subtotal + shippingCost
  );
};

const refreshQuote = async ()=>{
  const state = stateSelect?.value;
  if(!state || CartStore.RESTRICTED_STATES.includes(state)){
    renderSummary();
    return;
  }

  const requestId = ++quoteRequestId;

  try{
    const quote = await ApiClient.quote({
      items: CartStore.getItems(),
      state
    });
    if(requestId !== quoteRequestId) return;
    renderSummary(quote);
  }catch{
    if(requestId !== quoteRequestId) return;
    renderSummary();
  }
};

const digitsOnly = (value)=>value.replace(/\D/g, "");

const formatCardNumber = (value)=>{
  const digits = digitsOnly(value).slice(0, 16);
  return digits.replace(/(\d{4})(?=\d)/g, "$1 ").trim();
};

const formatExpiry = (value)=>{
  const digits = digitsOnly(value).slice(0, 4);
  if(digits.length <= 2) return digits;
  return `${digits.slice(0, 2)} / ${digits.slice(2)}`;
};

const detectBrand = (number)=>{
  if(/^4/.test(number)) return "Visa";
  if(/^5[1-5]/.test(number)) return "Mastercard";
  if(/^3[47]/.test(number)) return "Amex";
  if(/^6/.test(number)) return "Discover";
  return "Card";
};

const validateExpiry = (value)=>{
  const match = value.match(/^(\d{2})\s*\/\s*(\d{2})$/);
  if(!match) return false;
  const month = Number(match[1]);
  const year = 2000 + Number(match[2]);
  if(month < 1 || month > 12) return false;
  const expiry = new Date(year, month);
  return expiry > new Date();
};

const trim = (value)=>String(value ?? "").trim();

const validateForm = (data)=>{
  if(!trim(data.email) || !EMAIL_RE.test(trim(data.email))){
    return "Enter a valid email address.";
  }
  if(!trim(data.phone) || !PHONE_RE.test(trim(data.phone))){
    return "Enter a valid phone number.";
  }
  if(!trim(data.name) || trim(data.name).length < 2){
    return "Enter your full name.";
  }
  if(!trim(data.address1) || trim(data.address1).length < 4){
    return "Enter a valid street address.";
  }
  if(!trim(data.city)){
    return "Enter a city.";
  }
  if(!trim(data.state)){
    return "Select a state.";
  }
  if(CartStore.RESTRICTED_STATES.includes(data.state)){
    return "We cannot ship CBD products to your state.";
  }
  if(!ZIP_RE.test(trim(data.zip))){
    return "Enter a valid ZIP code.";
  }
  if(!trim(data.cardName)){
    return "Enter the name on your card.";
  }

  const cardNumber = digitsOnly(data.cardNumber);
  if(cardNumber.length < 15){
    return "Enter a valid card number.";
  }
  if(!validateExpiry(data.cardExpiry)){
    return "Enter a valid expiry date (MM / YY).";
  }
  if(digitsOnly(data.cardCvc).length < 3){
    return "Enter a valid CVC.";
  }
  if(!data.acceptTerms){
    return "Please accept the terms to place your order.";
  }
  if(data.createAccount && !signedInUser){
    if(String(data.accountPassword || "").length < 8){
      return "Account password must be at least 8 characters.";
    }
    if(data.accountPassword !== data.accountPasswordConfirm){
      return "Account passwords do not match.";
    }
  }
  return "";
};

const resetSubmitState = ()=>{
  submitBtn.disabled = false;
  submitBtn.classList.remove("is-loading");
  submitBtn.querySelector(".checkout-submit-label")?.removeAttribute("hidden");
  submitBtn.querySelector(".checkout-submit-loading")?.setAttribute("hidden", "");
};

form?.querySelector("[data-card-number]")?.addEventListener("input", e=>{
  e.target.value = formatCardNumber(e.target.value);
});

form?.querySelector("[data-card-expiry]")?.addEventListener("input", e=>{
  e.target.value = formatExpiry(e.target.value);
});

form?.querySelector("[data-card-cvc]")?.addEventListener("input", e=>{
  e.target.value = digitsOnly(e.target.value).slice(0, 4);
});

stateSelect?.addEventListener("change", ()=>{
  const restricted = CartStore.RESTRICTED_STATES.includes(stateSelect.value);
  stateError.hidden = !restricted;
  stateError.textContent = restricted
    ? "CBD products cannot be shipped to this state."
    : "";
  refreshQuote();
});

form?.addEventListener("submit", async e=>{
  e.preventDefault();
  formError.hidden = true;
  stateError.hidden = true;

  const fd = new FormData(form);
  const payload = Object.fromEntries(fd.entries());
  payload.acceptTerms = fd.get("acceptTerms") === "on";
  payload.createAccount = fd.get("createAccount") === "on";
  payload.state = payload.state || stateSelect?.value;

  const validationError = validateForm(payload);

  if(validationError){
    formError.textContent = validationError;
    formError.hidden = false;
    if(CartStore.RESTRICTED_STATES.includes(payload.state)){
      stateError.textContent = validationError;
      stateError.hidden = false;
    }
    return;
  }

  let newsletterOptIn = false;
  try{
    newsletterOptIn = await Newsletter.offer({
      email: trim(payload.email),
      name: trim(payload.name),
      source: "checkout",
      known: Boolean(signedInUser?.newsletter)
    }) === true;
  }catch{
    newsletterOptIn = false;
  }

  submitBtn.disabled = true;
  submitBtn.classList.add("is-loading");
  submitBtn.querySelector(".checkout-submit-label")?.setAttribute("hidden", "");
  submitBtn.querySelector(".checkout-submit-loading")?.removeAttribute("hidden");

  const cardNumber = digitsOnly(payload.cardNumber);

  try{
    if(payload.createAccount && !signedInUser){
      await ApiClient.accountRegister({
        email: trim(payload.email),
        password: payload.accountPassword,
        name: trim(payload.name),
        phone: trim(payload.phone),
        newsletter: newsletterOptIn,
        shipping: {
          address1: trim(payload.address1),
          address2: trim(payload.address2 || ""),
          city: trim(payload.city),
          state: trim(payload.state),
          zip: trim(payload.zip)
        }
      });
    }

    const result = await ApiClient.createOrder({
      items: CartStore.getItems(),
      acceptTerms: true,
      customer: {
        email: trim(payload.email),
        phone: trim(payload.phone),
        name: trim(payload.name)
      },
      shipping: {
        name: trim(payload.name),
        address1: trim(payload.address1),
        address2: trim(payload.address2 || ""),
        city: trim(payload.city),
        state: trim(payload.state),
        zip: trim(payload.zip)
      },
      paymentMethod: {
        brand: detectBrand(cardNumber),
        last4: cardNumber.slice(-4)
      }
    });

    CartStore.clearCart();
    window.location.href = `../order?id=${encodeURIComponent(result.order.id)}`;
  }catch(err){
    formError.textContent = err.message || "Unable to place order.";
    formError.hidden = false;
    resetSubmitState();
  }
});

const accountBar = document.querySelector("[data-checkout-account]");
const guestAccount = document.querySelector("[data-guest-account]");
const createAccountCheck = document.querySelector("[data-create-account]");
const createAccountFields = document.querySelector("[data-create-account-fields]");

const applyUserToForm = (user)=>{
  if(!form || !user) return;
  if(user.email) form.email.value = user.email;
  if(user.phone) form.phone.value = user.phone;
  if(user.name) form.name.value = user.name;
  const shipping = user.shipping || {};
  if(shipping.address1) form.address1.value = shipping.address1;
  if(shipping.address2) form.address2.value = shipping.address2;
  if(shipping.city) form.city.value = shipping.city;
  if(shipping.state){
    stateSelect.value = shipping.state;
    stateSelect.dispatchEvent(new Event("change"));
  }
  if(shipping.zip) form.zip.value = shipping.zip;
};

createAccountCheck?.addEventListener("change", ()=>{
  if(!createAccountFields) return;
  createAccountFields.hidden = !createAccountCheck.checked;
});

(async ()=>{
  try{
    const session = await ApiClient.accountSession();
    if(session.authenticated && session.user){
      signedInUser = session.user;
      if(guestAccount) guestAccount.hidden = true;
      if(accountBar){
        accountBar.hidden = false;
        accountBar.innerHTML = `Signed in as <strong>${session.user.email}</strong>. Review and complete the fields below. <a href="../account">Manage account</a>`;
      }
      applyUserToForm(session.user);
      return;
    }
  }catch{
    signedInUser = null;
  }

  if(guestAccount) guestAccount.hidden = false;
  if(accountBar){
    const next = encodeURIComponent(window.location.pathname.replace(/\/$/, "") || "/checkout");
    accountBar.hidden = false;
    accountBar.innerHTML = `Checking out as a guest. <a href="../account/login?next=${next}">Sign in</a> or <a href="../account/register?next=${next}">create an account</a> to save details — you can still complete this order without one.`;
  }
})();

fillStates();
renderSummary();
window.addEventListener("cart:updated", ()=>{
  refreshQuote();
});
refreshQuote();
