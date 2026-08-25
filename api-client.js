const ApiClient = (()=>{
  const jsonHeaders = { "Content-Type": "application/json" };

  const scriptUrl = document.currentScript?.src || "";
  const baseDir = scriptUrl
    ? new URL(".", scriptUrl).pathname.replace(/\/$/, "")
    : "";
  const apiRoot = `${baseDir}/api`.replace(/\/{2,}/g, "/") || "/api";
  const siteRoot = baseDir || "";

  const request = async (path, options = {})=>{
    const response = await fetch(`${apiRoot}${path}`, {
      ...options,
      credentials: "same-origin",
      headers: {
        ...jsonHeaders,
        ...(options.headers || {})
      }
    });

    const data = await response.json().catch(()=>({}));
    if(!response.ok){
      const error = new Error(data.error || "Request failed.");
      error.status = response.status;
      throw error;
    }
    return data;
  };

  const quote = ({ items, state })=>request("/quote", {
    method: "POST",
    body: JSON.stringify({ items, state })
  });

  const createOrder = (payload)=>request("/orders/create", {
    method: "POST",
    body: JSON.stringify(payload)
  });

  const getOrder = (orderId)=>request(`/orders/${encodeURIComponent(orderId)}`);

  const accountSession = ()=>request("/account/session");

  const accountRegister = (payload)=>request("/account/register", {
    method: "POST",
    body: JSON.stringify(payload)
  });

  const accountLogin = (payload)=>request("/account/login", {
    method: "POST",
    body: JSON.stringify(payload)
  });

  const accountLogout = ()=>request("/account/logout", { method: "POST" });

  const accountUpdate = (payload)=>request("/account/update", {
    method: "PATCH",
    body: JSON.stringify(payload)
  });

  const accountOrders = ()=>request("/account/orders");

  const newsletterStatus = (email)=>request(`/newsletter/status?email=${encodeURIComponent(email)}`);

  const newsletterSubscribe = (payload)=>request("/newsletter/subscribe", {
    method: "POST",
    body: JSON.stringify(payload)
  });

  const contactSend = (payload)=>request("/contact", {
    method: "POST",
    body: JSON.stringify(payload)
  });

  return {
    apiRoot,
    siteRoot,
    quote,
    createOrder,
    getOrder,
    accountSession,
    accountRegister,
    accountLogin,
    accountLogout,
    accountUpdate,
    accountOrders,
    newsletterStatus,
    newsletterSubscribe,
    contactSend
  };
})();
