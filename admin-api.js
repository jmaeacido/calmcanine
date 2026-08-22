const AdminApi = (()=>{
  const jsonHeaders = { "Content-Type": "application/json" };

  // Resolve /calmcanine/api (or /api) from this script's URL so subdirectory installs work.
  const scriptUrl = document.currentScript?.src || "";
  const baseDir = scriptUrl
    ? new URL(".", scriptUrl).pathname.replace(/\/$/, "")
    : "";
  const apiRoot = `${baseDir}/api`.replace(/\/{2,}/g, "/") || "/api";

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

  return {
    apiRoot,
    session: ()=>request("/admin/session"),
    login: (password)=>request("/admin/login", {
      method: "POST",
      body: JSON.stringify({ password })
    }),
    logout: ()=>request("/admin/logout", { method: "POST" }),
    listOrders: ()=>request("/admin/orders"),
    getOrder: (orderId)=>request(`/admin/orders/${encodeURIComponent(orderId)}`),
    updateFulfillment: (orderId, status)=>request(`/admin/orders/${encodeURIComponent(orderId)}/fulfillment`, {
      method: "PATCH",
      body: JSON.stringify({ status })
    }),
    markEmailSent: (orderId, kind)=>request(
      `/admin/orders/${encodeURIComponent(orderId)}/emails/${encodeURIComponent(kind)}/mark-sent`,
      { method: "POST" }
    ),
    requeueEmail: (orderId, kind)=>request(
      `/admin/orders/${encodeURIComponent(orderId)}/emails/${encodeURIComponent(kind)}/requeue`,
      { method: "POST" }
    )
  };
})();
