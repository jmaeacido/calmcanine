(()=>{
  const form = document.querySelector("[data-contact-form]");
  const submit = form?.querySelector("[data-contact-submit]");
  const error = form?.querySelector("[data-contact-error]");
  const success = form?.querySelector("[data-contact-success]");

  form?.addEventListener("submit", async event=>{
    event.preventDefault();
    error.hidden = true;
    success.hidden = true;
    if(!form.checkValidity()){
      form.reportValidity();
      return;
    }

    const data = Object.fromEntries(new FormData(form));
    submit.disabled = true;
    submit.classList.add("is-loading");
    submit.textContent = "Sending…";
    try{
      const result = await ApiClient.contactSend(data);
      form.reset();
      success.textContent = result.message || "Thanks — your message has been sent.";
      success.hidden = false;
    }catch(err){
      error.textContent = err.message || "Unable to send your message. Please try again.";
      error.hidden = false;
    }finally{
      submit.disabled = false;
      submit.classList.remove("is-loading");
      submit.textContent = "Send message";
    }
  });
})();
