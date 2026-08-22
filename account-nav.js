(()=>{
  const link = document.querySelector("[data-account-nav]");
  if(!link || typeof ApiClient === "undefined") return;

  const root = ApiClient.siteRoot || "";
  const accountHref = `${root}/account`.replace(/\/{2,}/g, "/") || "/account";
  const loginHref = `${root}/account/login`.replace(/\/{2,}/g, "/") || "/account/login";

  ApiClient.accountSession().then(data=>{
    if(data.authenticated && data.user){
      const name = String(data.user.name || "").trim();
      const first = name.split(/\s+/)[0];
      link.href = accountHref;
      link.textContent = first || "Account";
      link.setAttribute("aria-current", link.getAttribute("data-account-current") === "true" ? "page" : null);
      if(link.getAttribute("data-account-current") !== "true"){
        link.removeAttribute("aria-current");
      }
      return;
    }
    link.href = loginHref;
    link.textContent = "Sign in";
    link.removeAttribute("aria-current");
  }).catch(()=>{
    link.href = loginHref;
    link.textContent = "Sign in";
  });
})();
