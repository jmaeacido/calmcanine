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

const main = document.querySelector("[data-account-main]");
const form = document.querySelector("[data-profile-form]");
const stateSelect = document.querySelector("[data-state-select]");
const errorEl = document.querySelector("[data-form-error]");
const successEl = document.querySelector("[data-form-success]");
const ordersEl = document.querySelector("[data-orders]");
const ordersEmpty = document.querySelector("[data-orders-empty]");
const loginHref = `${ApiClient.siteRoot}/account/login`;

US_STATES.forEach(([code, name])=>{
  const option = document.createElement("option");
  option.value = code;
  option.textContent = name;
  stateSelect?.appendChild(option);
});

const fillProfile = (user)=>{
  form.email.value = user.email || "";
  form.name.value = user.name || "";
  form.phone.value = user.phone || "";
  form.address1.value = user.shipping?.address1 || "";
  form.address2.value = user.shipping?.address2 || "";
  form.city.value = user.shipping?.city || "";
  form.state.value = user.shipping?.state || "";
  form.zip.value = user.shipping?.zip || "";
};

const formatDate = (iso)=>{
  if(!iso) return "";
  const date = new Date(iso);
  if(Number.isNaN(date.getTime())) return iso;
  return date.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });
};

const renderOrders = (orders)=>{
  const list = Array.isArray(orders) ? orders : [];
  ordersEmpty.hidden = list.length > 0;
  ordersEl.innerHTML = list.map(order=>`
    <a class="account-order-card" href="../order?id=${encodeURIComponent(order.id)}">
      <strong>${order.id}</strong>
      <span>${formatDate(order.createdAt)}</span>
      <span>${CartStore.formatMoney(order.total || 0)}</span>
    </a>
  `).join("");
};

(async ()=>{
  try{
    const session = await ApiClient.accountSession();
    if(!session.authenticated){
      window.location.replace(loginHref);
      return;
    }
    fillProfile(session.user);
    main.hidden = false;
    const { orders } = await ApiClient.accountOrders();
    renderOrders(orders);
  }catch{
    window.location.replace(loginHref);
  }
})();

form?.addEventListener("submit", async e=>{
  e.preventDefault();
  errorEl.hidden = true;
  successEl.hidden = true;
  const fd = new FormData(form);
  try{
    const result = await ApiClient.accountUpdate({
      name: String(fd.get("name") || "").trim(),
      phone: String(fd.get("phone") || "").trim(),
      shipping: {
        address1: String(fd.get("address1") || "").trim(),
        address2: String(fd.get("address2") || "").trim(),
        city: String(fd.get("city") || "").trim(),
        state: String(fd.get("state") || "").trim(),
        zip: String(fd.get("zip") || "").trim()
      }
    });
    fillProfile(result.user);
    successEl.hidden = false;
  }catch(err){
    errorEl.textContent = err.message || "Unable to save.";
    errorEl.hidden = false;
  }
});

document.querySelector("[data-logout]")?.addEventListener("click", async ()=>{
  try{
    await ApiClient.accountLogout();
  }finally{
    window.location.href = loginHref;
  }
});
