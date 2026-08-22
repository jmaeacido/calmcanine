const form = document.querySelector("[data-login-form]");
const errorEl = document.querySelector("[data-form-error]");
const submitBtn = document.querySelector("[data-submit]");
const registerLink = document.querySelector("[data-register-link]");

const next = new URLSearchParams(window.location.search).get("next") || "";
if(registerLink && next){
  registerLink.href = `../register?next=${encodeURIComponent(next)}`;
}

const safeNext = (value)=>{
  if(!value || !value.startsWith("/") || value.startsWith("//")) return `${ApiClient.siteRoot}/account`;
  return value;
};

ApiClient.accountSession().then(data=>{
  if(data.authenticated){
    window.location.replace(safeNext(next));
  }
}).catch(()=>{});

form?.addEventListener("submit", async e=>{
  e.preventDefault();
  errorEl.hidden = true;
  const fd = new FormData(form);
  submitBtn.disabled = true;
  try{
    await ApiClient.accountLogin({
      email: String(fd.get("email") || "").trim(),
      password: String(fd.get("password") || "")
    });
    window.location.href = safeNext(next);
  }catch(err){
    errorEl.textContent = err.message || "Unable to sign in.";
    errorEl.hidden = false;
    submitBtn.disabled = false;
  }
});
