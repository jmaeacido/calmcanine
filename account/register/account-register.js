const form = document.querySelector("[data-register-form]");
const errorEl = document.querySelector("[data-form-error]");
const submitBtn = document.querySelector("[data-submit]");
const loginLink = document.querySelector("[data-login-link]");

const next = new URLSearchParams(window.location.search).get("next") || "";
if(loginLink && next){
  loginLink.href = `../login?next=${encodeURIComponent(next)}`;
}

const safeNext = (value)=>{
  if(!value || !value.startsWith("/") || value.startsWith("//")) return `${ApiClient.siteRoot}/account`;
  return value;
};

ApiClient.accountSession().then(data=>{
  if(data.authenticated){
    window.location.replace(safeNext(next) || `${ApiClient.siteRoot}/account`);
  }
}).catch(()=>{});

form?.addEventListener("submit", async e=>{
  e.preventDefault();
  errorEl.hidden = true;
  const fd = new FormData(form);
  const name = String(fd.get("name") || "").trim();
  const email = String(fd.get("email") || "").trim();
  const password = String(fd.get("password") || "");
  const confirm = String(fd.get("confirm") || "");

  if(name.length < 2){
    errorEl.textContent = "Enter your full name.";
    errorEl.hidden = false;
    return;
  }
  if(password.length < 8){
    errorEl.textContent = "Password must be at least 8 characters.";
    errorEl.hidden = false;
    return;
  }
  if(password !== confirm){
    errorEl.textContent = "Passwords do not match.";
    errorEl.hidden = false;
    return;
  }

  submitBtn.disabled = true;
  try{
    const subscribed = await Newsletter.offer({
      email,
      name,
      source: "register",
    });
    await ApiClient.accountRegister({ email, password, name, newsletter: subscribed === true });
    window.location.href = safeNext(next);
  }catch(err){
    errorEl.textContent = err.message || "Unable to create account.";
    errorEl.hidden = false;
    submitBtn.disabled = false;
  }
});
