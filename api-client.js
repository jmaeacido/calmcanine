const ApiClient = (()=>{
  const jsonHeaders = { "Content-Type": "application/json" };

  const request = async (path, options = {})=>{
    const response = await fetch(path, {
      ...options,
      headers: {
        ...jsonHeaders,
        ...(options.headers || {})
      }
    });

    const data = await response.json().catch(()=>({}));
    if(!response.ok){
      throw new Error(data.error || "Request failed.");
    }
    return data;
  };

  const quote = ({ items, state })=>request("/api/quote", {
    method: "POST",
    body: JSON.stringify({ items, state })
  });

  const createOrder = (payload)=>request("/api/orders/create", {
    method: "POST",
    body: JSON.stringify(payload)
  });

  const getOrder = (orderId)=>request(`/api/orders/${encodeURIComponent(orderId)}`);

  return {
    quote,
    createOrder,
    getOrder
  };
})();
