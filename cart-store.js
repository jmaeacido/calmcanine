const CartStore = (()=>{
  const CART_KEY = "calmcanine_cart";

  const PRODUCT = {
    id: "calm-canine",
    name: "Calm Canine",
    sku: "617395758661",
    image: "assets/calm-canine-pouch-v2.png",
    prices: {
      onetime: 19.99,
      subscribe: 17.99
    }
  };

  const SHIPPING_FLAT = 5.99;
  const FREE_SHIPPING_MIN = 35;

  const RESTRICTED_STATES = [
    "AL", "ID", "KS", "MS", "NE", "ND", "SC", "WY"
  ];

  const readCart = ()=>{
    try{
      const raw = localStorage.getItem(CART_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    }catch{
      return [];
    }
  };

  const writeCart = (items)=>{
    localStorage.setItem(CART_KEY, JSON.stringify(items));
    syncBadge();
    window.dispatchEvent(new CustomEvent("cart:updated", { detail: { items } }));
  };

  const lineKey = (item)=>`${item.productId}:${item.purchaseType}`;

  const normalizeItem = (item)=>({
    productId: item.productId || PRODUCT.id,
    name: item.name || PRODUCT.name,
    sku: item.sku || PRODUCT.sku,
    image: item.image || PRODUCT.image,
    purchaseType: item.purchaseType === "subscribe" ? "subscribe" : "onetime",
    deliveryPlan: item.deliveryPlan || "1_month",
    quantity: Math.min(10, Math.max(1, Number(item.quantity) || 1)),
    unitPrice: item.purchaseType === "subscribe" ? PRODUCT.prices.subscribe : PRODUCT.prices.onetime
  });

  const getItems = ()=>readCart().map(normalizeItem);

  const getCount = ()=>getItems().reduce((sum, item)=>sum + item.quantity, 0);

  const getLineTotal = (item)=>item.unitPrice * item.quantity;

  const getSubtotal = ()=>getItems().reduce((sum, item)=>sum + getLineTotal(item), 0);

  const getShipping = ()=>{
    const subtotal = getSubtotal();
    return subtotal >= FREE_SHIPPING_MIN ? 0 : (subtotal > 0 ? SHIPPING_FLAT : 0);
  };

  const getTotal = ()=>getSubtotal() + getShipping();

  const formatMoney = (amount)=>new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD"
  }).format(amount);

  const purchaseLabel = (type)=>type === "subscribe" ? "Subscribe & save" : "One-time purchase";

  const addItem = (input)=>{
    const item = normalizeItem(input);
    const items = getItems();
    const key = lineKey(item);
    const existing = items.find(i=>lineKey(i) === key);

    if(existing){
      existing.quantity = Math.min(10, existing.quantity + item.quantity);
    }else{
      items.push(item);
    }

    writeCart(items);
    return items;
  };

  const updateQuantity = (productId, purchaseType, quantity)=>{
    const items = getItems();
    const key = lineKey({ productId, purchaseType });
    const qty = Math.min(10, Math.max(0, Number(quantity) || 0));

    const next = items
      .map(item=>lineKey(item) === key ? { ...item, quantity: qty } : item)
      .filter(item=>item.quantity > 0);

    writeCart(next);
    return next;
  };

  const removeItem = (productId, purchaseType)=>{
    return updateQuantity(productId, purchaseType, 0);
  };

  const clearCart = ()=>writeCart([]);

  const resolveImage = (imagePath, baseDepth = 0)=>{
    const prefix = baseDepth === 0 ? "" : "../".repeat(baseDepth);
    if(imagePath.startsWith("http") || imagePath.startsWith("../") || imagePath.startsWith("/")){
      return imagePath;
    }
    return `${prefix}${imagePath}`;
  };

  const syncBadge = ()=>{
    const count = getCount();
    document.querySelectorAll("[data-cart-count]").forEach(el=>{
      el.textContent = String(count);
      el.hidden = count === 0;
    });
  };

  document.addEventListener("DOMContentLoaded", syncBadge);

  return {
    PRODUCT,
    RESTRICTED_STATES,
    FREE_SHIPPING_MIN,
    getItems,
    getCount,
    getSubtotal,
    getShipping,
    getTotal,
    getLineTotal,
    formatMoney,
    purchaseLabel,
    addItem,
    updateQuantity,
    removeItem,
    clearCart,
    resolveImage,
    syncBadge
  };
})();
