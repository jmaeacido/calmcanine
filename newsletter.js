const Newsletter = (()=>{
  const STORAGE_KEY = "calmcanine_newsletter_emails";

  const readEmails = ()=>{
    try{
      const parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || "[]");
      return Array.isArray(parsed) ? parsed.map(value=>String(value).toLowerCase()) : [];
    }catch{
      return [];
    }
  };

  const remember = (email)=>{
    const key = String(email || "").trim().toLowerCase();
    if(!key) return;
    const emails = readEmails();
    if(!emails.includes(key)){
      emails.push(key);
      localStorage.setItem(STORAGE_KEY, JSON.stringify(emails));
    }
  };

  const remembered = (email)=>readEmails().includes(String(email || "").trim().toLowerCase());

  let modal;
  let errorEl;
  let yesBtn;
  let skipBtn;
  let lastFocus = null;
  let pending = null;

  const ensureModal = ()=>{
    if(modal) return modal;
    modal = document.createElement("div");
    modal.className = "newsletter-modal";
    modal.hidden = true;
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    modal.setAttribute("aria-labelledby", "newsletter-title");
    modal.innerHTML = `
      <div class="newsletter-modal-card">
        <p class="eyebrow">OPTIONAL</p>
        <h2 id="newsletter-title">Stay in the calm loop.</h2>
        <p class="newsletter-modal-copy">Get occasional notes on restocks and gentle routines. This is optional — your account or order will continue either way.</p>
        <p class="field-error" data-newsletter-error hidden></p>
        <div class="newsletter-modal-actions">
          <button type="button" class="btn btn-primary" data-newsletter-yes>Subscribe</button>
          <button type="button" class="btn btn-ghost" data-newsletter-skip>No thanks</button>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    errorEl = modal.querySelector("[data-newsletter-error]");
    yesBtn = modal.querySelector("[data-newsletter-yes]");
    skipBtn = modal.querySelector("[data-newsletter-skip]");

    yesBtn.addEventListener("click", async ()=>{
      if(!pending) return;
      errorEl.hidden = true;
      yesBtn.disabled = true;
      skipBtn.disabled = true;
      try{
        await ApiClient.newsletterSubscribe({
          email: pending.email,
          name: pending.name || "",
          source: pending.source || "storefront"
        });
        remember(pending.email);
        close(true);
      }catch(err){
        errorEl.textContent = err.message || "Unable to subscribe right now.";
        errorEl.hidden = false;
        yesBtn.disabled = false;
        skipBtn.disabled = false;
      }
    });

    skipBtn.addEventListener("click", ()=>close(false));
    modal.addEventListener("click", e=>{
      if(e.target === modal) close(false);
    });
    document.addEventListener("keydown", e=>{
      if(e.key === "Escape" && modal && !modal.hidden){
        e.preventDefault();
        close(false);
      }
    });
    return modal;
  };

  const close = (subscribed)=>{
    if(!modal || modal.hidden) return;
    modal.hidden = true;
    document.body.classList.remove("newsletter-modal-open");
    yesBtn.disabled = false;
    skipBtn.disabled = false;
    lastFocus?.focus?.();
    const resolver = pending?.resolve;
    pending = null;
    resolver?.(subscribed);
  };

  const open = ({ email, name, source })=>new Promise(resolve=>{
    ensureModal();
    lastFocus = document.activeElement;
    pending = { email, name, source, resolve };
    errorEl.hidden = true;
    modal.hidden = false;
    document.body.classList.add("newsletter-modal-open");
    yesBtn.focus();
  });

  const alreadySubscribed = async ({ email, known } = {})=>{
    if(known === true){
      remember(email);
      return true;
    }
    if(remembered(email)) return true;
    try{
      const result = await ApiClient.newsletterStatus(email);
      if(result.subscribed){
        remember(email);
        return true;
      }
    }catch{
      // Fall through to the prompt if status cannot be checked.
    }
    return false;
  };

  const offer = async ({ email, name = "", source = "storefront", known = false } = {})=>{
    const trimmed = String(email || "").trim();
    if(!trimmed) return false;
    if(await alreadySubscribed({ email: trimmed, known })) return true;
    return open({ email: trimmed, name, source });
  };

  return { offer, remembered, remember };
})();
