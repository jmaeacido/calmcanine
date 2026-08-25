(()=>{
  const money = (value)=>`$${Number(value || 0).toFixed(2)}`;
  const escapeHtml = (value)=>String(value ?? "").replace(/[&<>"']/g, (char)=>({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
  })[char]);
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

  const params = new URLSearchParams(window.location.search);
  const orderId = params.get("id") || "";

  const loadingEl = document.querySelector("[data-order-loading]");
  const missingEl = document.querySelector("[data-order-missing]");
  const detailEl = document.querySelector("[data-order-detail]");
  const errorEl = document.querySelector("[data-action-error]");
  const okEl = document.querySelector("[data-action-ok]");

  let currentOrder = null;

  const showError = (message)=>{
    okEl.hidden = true;
    errorEl.textContent = message;
    errorEl.hidden = !message;
  };

  const showOk = (message)=>{
    errorEl.hidden = true;
    okEl.textContent = message;
    okEl.hidden = !message;
  };

  const emailStatusText = (flags, kind)=>{
    const part = flags?.[kind] || {};
    if (part.sent) return "Sent";
    if (part.error) return "Send failed";
    if (part.queued) return "Waiting to send";
    return "Not queued yet";
  };

  const emailStatusKey = (flags, kind)=>{
    const part = flags?.[kind] || {};
    if (part.sent) return "sent";
    if (part.error) return "failed";
    if (part.queued) return "queued";
    return "idle";
  };

  const emailKindLabel = (kind)=> kind === "ops" ? "Internal alert" : "Order confirmation";

  const renderEmailDetails = (target, order, kind)=>{
    const job = order.emailJobs?.[kind];
    const rows = [];

    if (!job) {
      target.innerHTML = `<div><dt></dt><dd class="admin-muted">No email details on file.</dd></div>`;
      return;
    }

    const to = job.to || (kind === "customer" ? order.customer?.email : "") || "—";
    rows.push(`<div><dt>To</dt><dd>${escapeHtml(to)}</dd></div>`);

    if (job.subject) {
      rows.push(`<div><dt>Subject</dt><dd>${escapeHtml(job.subject)}</dd></div>`);
    }
    if (job.preview) {
      rows.push(`<div><dt>Preview</dt><dd class="admin-email-preview-text">${escapeHtml(job.preview)}</dd></div>`);
    }
    if (kind === "ops" && job.total != null && !job.preview) {
      rows.push(`<div><dt>Order total</dt><dd>${money(job.total)}</dd></div>`);
    }
    if (job.error) {
      rows.push(`<div><dt>Error</dt><dd class="admin-email-error">${escapeHtml(job.error)}</dd></div>`);
    }
    if (job.queuedAt) {
      rows.push(`<div><dt>Queued</dt><dd>${formatDate(job.queuedAt)}</dd></div>`);
    }
    if (job.sentAt) {
      rows.push(`<div><dt>Sent</dt><dd>${formatDate(job.sentAt)}</dd></div>`);
    }

    target.innerHTML = rows.join("");
  };

  const render = (order)=>{
    currentOrder = order;
    loadingEl.hidden = true;
    missingEl.hidden = true;
    detailEl.hidden = false;

    document.querySelector("[data-order-id]").textContent = order.id;
    document.querySelector("[data-order-date]").textContent = formatDate(order.createdAt);
    const orderStatus = document.querySelector("[data-order-status]");
    orderStatus.textContent = order.status || "—";
    orderStatus.dataset.status = order.status || "unknown";

    document.querySelector("[data-customer-name]").textContent = order.customer?.name || "—";
    document.querySelector("[data-customer-email]").textContent = order.customer?.email || "—";
    document.querySelector("[data-customer-phone]").textContent = order.customer?.phone || "—";
    const guestEl = document.querySelector("[data-customer-guest]");
    if (guestEl) guestEl.textContent = order.guest ? "Guest" : "Signed-in account";

    const ship = order.shipping || {};
    const lines = [
      ship.name,
      ship.address1,
      ship.address2,
      [ship.city, ship.state, ship.zip].filter(Boolean).join(", ")
    ].filter(Boolean);
    document.querySelector("[data-shipping-address]").innerHTML = lines.map((l)=>`${l}<br>`).join("");

    document.querySelector("[data-order-lines]").innerHTML = (order.items || []).map((item)=>`
      <article class="cart-line cart-line--readonly">
        <div class="cart-line-copy">
          <h3>${item.name || "Item"}</h3>
          <p>SKU ${item.sku || "—"} · ${item.purchaseType === "subscribe" ? `Subscribe (${item.deliveryPlan || "1_month"})` : "One-time"} · Qty ${item.quantity}</p>
        </div>
        <p class="cart-line-price">${money(item.lineTotal ?? (item.unitPrice * item.quantity))}</p>
      </article>
    `).join("") || "<p class=\"admin-muted\">No items</p>";

    const subs = order.subscriptions || [];
    const subsPanel = document.querySelector("[data-subscriptions-panel]");
    if (subs.length) {
      subsPanel.hidden = false;
      document.querySelector("[data-subscriptions]").innerHTML = subs.map((sub)=>`
        <li>${sub.sku || sub.productId} · plan ${sub.plan} · qty ${sub.quantity} · ${money(sub.unitPrice)} · ${sub.status}</li>
      `).join("");
    } else {
      subsPanel.hidden = true;
    }

    const pay = order.payment || {};
    document.querySelector("[data-payment-method]").textContent = `${pay.brand || "Card"} ···· ${pay.last4 || "----"}`;
    document.querySelector("[data-payment-provider]").textContent = pay.provider || "—";
    const paymentStatus = document.querySelector("[data-payment-status]");
    paymentStatus.textContent = pay.status || "—";
    paymentStatus.className = `admin-inline-status admin-inline-status--${pay.status || "unknown"}`;
    document.querySelector("[data-payment-reference]").textContent = pay.reference || "—";

    document.querySelector("[data-summary-subtotal]").textContent = money(order.subtotal);
    document.querySelector("[data-summary-shipping]").textContent = money(order.shippingCost);
    document.querySelector("[data-summary-tax]").textContent = money(order.tax);
    document.querySelector("[data-summary-total]").textContent = money(order.total);

    document.querySelector("[data-fulfillment-status]").value = order.fulfillment?.status || "pending";

    const customerEmailStatus = document.querySelector("[data-email-customer-status]");
    const opsEmailStatus = document.querySelector("[data-email-ops-status]");
    customerEmailStatus.textContent = emailStatusText(order.email, "customer");
    customerEmailStatus.dataset.status = emailStatusKey(order.email, "customer");
    opsEmailStatus.textContent = emailStatusText(order.email, "ops");
    opsEmailStatus.dataset.status = emailStatusKey(order.email, "ops");
    renderEmailDetails(document.querySelector("[data-email-customer-details]"), order, "customer");
    renderEmailDetails(document.querySelector("[data-email-ops-details]"), order, "ops");
  };

  document.querySelector("[data-copy-payment]")?.addEventListener("click", async (event)=>{
    const reference = currentOrder?.payment?.reference;
    if (!reference) return;
    try {
      await navigator.clipboard.writeText(reference);
      const button = event.currentTarget;
      button.textContent = "Copied";
      window.setTimeout(()=>{ button.textContent = "Copy"; }, 1400);
    } catch {
      showError("Could not copy the payment reference.");
    }
  });

  document.querySelector("[data-admin-logout]")?.addEventListener("click", async ()=>{
    try {
      await AdminApi.logout();
    } finally {
      window.location.href = "../login";
    }
  });

  document.querySelector("[data-save-fulfillment]")?.addEventListener("click", async ()=>{
    if (!currentOrder) return;
    const status = document.querySelector("[data-fulfillment-status]").value;
    try {
      const data = await AdminApi.updateFulfillment(currentOrder.id, status);
      render(data.order);
      showOk("Fulfillment status saved.");
    } catch (err) {
      showError(err.message || "Could not update fulfillment.");
    }
  });

  document.querySelectorAll("[data-mark-sent]").forEach((btn)=>{
    btn.addEventListener("click", async ()=>{
      if (!currentOrder) return;
      const kind = btn.getAttribute("data-mark-sent");
      try {
        const data = await AdminApi.markEmailSent(currentOrder.id, kind);
        render(data.order);
        showOk(`${emailKindLabel(kind)} marked as sent.`);
      } catch (err) {
        showError(err.message || "Could not mark email as sent.");
      }
    });
  });

  document.querySelectorAll("[data-requeue]").forEach((btn)=>{
    btn.addEventListener("click", async ()=>{
      if (!currentOrder) return;
      const kind = btn.getAttribute("data-requeue");
      try {
        const data = await AdminApi.requeueEmail(currentOrder.id, kind);
        render(data.order);
        showOk(`${emailKindLabel(kind)} sent.`);
      } catch (err) {
        showError(err.message || "Could not send email.");
      }
    });
  });

  (async ()=>{
    try {
      const session = await AdminApi.session();
      if (!session.authenticated) {
        window.location.replace("../login");
        return;
      }
      if (!orderId) {
        loadingEl.hidden = true;
        missingEl.hidden = false;
        return;
      }
      const data = await AdminApi.getOrder(orderId);
      render(data.order);
    } catch (err) {
      if (err.status === 404) {
        loadingEl.hidden = true;
        missingEl.hidden = false;
        return;
      }
      // Fail closed for auth/session/API failures
      window.location.replace("../login");
    }
  })();
})();
