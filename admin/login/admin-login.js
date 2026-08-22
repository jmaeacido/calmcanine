(async ()=>{
  const form = document.querySelector("[data-admin-login]");
  const errorEl = document.querySelector("[data-login-error]");
  const submitBtn = document.querySelector("[data-login-submit]");

  try {
    const session = await AdminApi.session();
    if (session.authenticated) {
      window.location.replace("../");
      return;
    }
  } catch (_) {
    /* show login form */
  }

  form?.addEventListener("submit", async (event)=>{
    event.preventDefault();
    errorEl.hidden = true;
    errorEl.textContent = "";
    submitBtn.disabled = true;

    const password = new FormData(form).get("password")?.toString() || "";
    try {
      await AdminApi.login(password);
      window.location.href = "../";
    } catch (err) {
      errorEl.textContent = err.message || "Sign in failed.";
      errorEl.hidden = false;
      submitBtn.disabled = false;
    }
  });
})();
