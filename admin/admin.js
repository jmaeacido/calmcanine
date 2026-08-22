(()=>{
  const money = (value)=>`$${Number(value || 0).toFixed(2)}`;
  const formatDate = (iso)=>{
    if (!iso) return "—";
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleString(undefined, {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "numeric",
      minute: "2-digit"
    });
  };

  const emailFlags = (email)=>{
    const customer = email?.customer?.sent ? "Cust sent" : (email?.customer?.queued ? "Cust queued" : "Cust —");
    const ops = email?.ops?.sent ? "Ops sent" : (email?.ops?.queued ? "Ops queued" : "Ops —");
    return `${customer} · ${ops}`;
  };

  let allOrders = [];

  const loadingEl = document.querySelector("[data-admin-loading]");
  const emptyEl = document.querySelector("[data-admin-empty]");
  const tablePanel = document.querySelector("[data-admin-table-panel]");
  const rowsEl = document.querySelector("[data-admin-rows]");
  const countEl = document.querySelector("[data-admin-count]");
  const searchEl = document.querySelector("[data-admin-search]");

  const render = (orders)=>{
    loadingEl.hidden = true;
    if (!orders.length) {
      tablePanel.hidden = true;
      emptyEl.hidden = false;
      countEl.textContent = "0 orders";
      return;
    }

    emptyEl.hidden = true;
    tablePanel.hidden = false;
    countEl.textContent = `${orders.length} order${orders.length === 1 ? "" : "s"}`;
    rowsEl.innerHTML = orders.map((order)=>`
      <tr class="admin-row" data-order-id="${order.id}">
        <td>
          <a class="admin-order-link" href="order?id=${encodeURIComponent(order.id)}">${order.id}</a>
          <div class="admin-muted">${formatDate(order.createdAt)}</div>
        </td>
        <td>
          <div>${order.customer?.name || "—"}</div>
          <div class="admin-muted">${order.customer?.email || ""}</div>
          <div class="admin-muted">${order.guest ? "Guest checkout" : "Account"}</div>
        </td>
        <td class="admin-items-cell">${order.itemSummary || "—"}</td>
        <td>${money(order.total)}</td>
        <td><span class="admin-status admin-status--${order.fulfillment?.status || "pending"}">${order.fulfillment?.status || "pending"}</span></td>
        <td class="admin-muted">${emailFlags(order.email)}</td>
      </tr>
    `).join("");
  };

  const filterOrders = ()=>{
    const q = (searchEl.value || "").trim().toLowerCase();
    if (!q) {
      render(allOrders);
      return;
    }
    render(allOrders.filter((order)=>{
      const id = (order.id || "").toLowerCase();
      const email = (order.customer?.email || "").toLowerCase();
      const name = (order.customer?.name || "").toLowerCase();
      return id.includes(q) || email.includes(q) || name.includes(q);
    }));
  };

  document.querySelector("[data-admin-logout]")?.addEventListener("click", async ()=>{
    try {
      await AdminApi.logout();
    } finally {
      window.location.href = "login";
    }
  });

  searchEl?.addEventListener("input", filterOrders);

  const requireLogin = ()=>{
    window.location.replace("login");
  };

  (async ()=>{
    try {
      const session = await AdminApi.session();
      if (!session.authenticated) {
        requireLogin();
        return;
      }
      const data = await AdminApi.listOrders();
      allOrders = data.orders || [];
      filterOrders();
    } catch (_) {
      // Fail closed: any session/API failure sends ops to login (not the admin shell).
      requireLogin();
    }
  })();
})();
