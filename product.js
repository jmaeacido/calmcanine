(()=>{
  const galleryStage = document.querySelector(".product-gallery-main");
  const galleryMain = document.querySelector("[data-gallery-main]");
  const galleryThumbs = [...document.querySelectorAll(".product-thumb")];

  const lockGalleryHeight = ()=>{
    if(!galleryStage) return;

    const styles = getComputedStyle(galleryStage);
    const padY = parseFloat(styles.paddingTop) + parseFloat(styles.paddingBottom);
    const innerWidth = galleryStage.clientWidth - parseFloat(styles.paddingLeft) - parseFloat(styles.paddingRight);
    const contentMaxWidth = Math.min(380, innerWidth);

    const sources = [...new Set([
      galleryMain?.getAttribute("src"),
      ...galleryThumbs.map(t=>t.dataset.gallerySrc)
    ].filter(Boolean))];

    Promise.all(sources.map(src=>new Promise(resolve=>{
      const img = new Image();
      img.onload = ()=>resolve(img);
      img.onerror = ()=>resolve(null);
      img.src = src;
    }))).then(images=>{
      let maxRenderedHeight = 0;
      images.forEach(img=>{
        if(!img?.naturalWidth) return;
        maxRenderedHeight = Math.max(
          maxRenderedHeight,
          contentMaxWidth * (img.naturalHeight / img.naturalWidth)
        );
      });
      if(maxRenderedHeight > 0){
        galleryStage.style.setProperty(
          "--gallery-preview-height",
          `${Math.ceil(maxRenderedHeight + padY)}px`
        );
      }
    });
  };

  if(galleryStage){
    lockGalleryHeight();
    window.addEventListener("resize", ()=>window.requestAnimationFrame(lockGalleryHeight));
  }

  if(galleryMain && galleryThumbs.length){
    galleryThumbs.forEach(thumb=>{
      thumb.addEventListener("click", ()=>{
        const src = thumb.dataset.gallerySrc;
        if(!src) return;
        galleryMain.src = src;
        galleryMain.classList.add("is-swapping");
        galleryThumbs.forEach(t=>t.classList.toggle("is-active", t === thumb));
        window.requestAnimationFrame(()=>{
          galleryMain.classList.remove("is-swapping");
        });
      });
    });
  }

  const purchaseRadios = [...document.querySelectorAll("[data-purchase-type]")];
  const priceCurrent = document.querySelector(".product-price-current");
  const priceNote = document.querySelector("[data-price-note]");
  const deliveryWrap = document.querySelector("[data-delivery-wrap]");

  const syncPurchaseSelection = ()=>{
    const selected = purchaseRadios.find(r=>r.checked);
    const isSubscribe = selected?.value === "subscribe";

    if(deliveryWrap){
      deliveryWrap.hidden = !isSubscribe;
    }

    if(!priceCurrent || !priceNote) return;

    if(isSubscribe){
      priceCurrent.innerHTML = "<s>$19.99</s> $17.99";
      priceNote.textContent = "Delivered every month — save 10%";
    }else{
      priceCurrent.textContent = "$19.99";
      priceNote.textContent = "One-time purchase";
    }
  };

  purchaseRadios.forEach(radio=>radio.addEventListener("change", syncPurchaseSelection));
  syncPurchaseSelection();

  const qtyInput = document.querySelector("[data-qty-input]");
  const qtyMinus = document.querySelector("[data-qty-minus]");
  const qtyPlus = document.querySelector("[data-qty-plus]");

  const clampQty = (value)=>{
    const min = Number(qtyInput?.min || 1);
    const max = Number(qtyInput?.max || 10);
    return Math.min(max, Math.max(min, value));
  };

  if(qtyInput){
    qtyMinus?.addEventListener("click", ()=>{
      qtyInput.value = clampQty(Number(qtyInput.value) - 1);
    });
    qtyPlus?.addEventListener("click", ()=>{
      qtyInput.value = clampQty(Number(qtyInput.value) + 1);
    });
    qtyInput.addEventListener("change", ()=>{
      qtyInput.value = clampQty(Number(qtyInput.value) || 1);
    });
  }

  const addForm = document.querySelector("[data-add-form]");
  const addToCartBtn = document.querySelector("[data-add-to-cart]");

  if(addForm && addToCartBtn){
    addForm.addEventListener("submit", e=>{
      e.preventDefault();

      const purchaseType = purchaseRadios.find(r=>r.checked)?.value || "onetime";
      const deliveryPlan = addForm.querySelector("[name='deliveryPlan']")?.value || "1_month";

      addToCartBtn.disabled = true;
      addToCartBtn.classList.add("is-loading");
      addToCartBtn.querySelector(".btn-add-cart-label")?.setAttribute("hidden", "");
      addToCartBtn.querySelector(".btn-add-cart-loading")?.removeAttribute("hidden");

      CartStore.addItem({
        productId: CartStore.PRODUCT.id,
        name: CartStore.PRODUCT.name,
        sku: CartStore.PRODUCT.sku,
        image: CartStore.PRODUCT.image,
        purchaseType,
        deliveryPlan,
        quantity: clampQty(Number(qtyInput?.value || 1))
      });

      window.location.href = "../cart";
    });
  }

  const tablist = document.querySelector(".product-tablist");
  const tabs = tablist ? [...tablist.querySelectorAll("[role='tab']")] : [];
  const panels = [...document.querySelectorAll(".product-panel")];

  const activateTab = (tab)=>{
    tabs.forEach(t=>{
      const active = t === tab;
      t.classList.toggle("is-active", active);
      t.setAttribute("aria-selected", active ? "true" : "false");
      t.tabIndex = active ? 0 : -1;
    });
    panels.forEach(panel=>{
      const active = panel.id === tab.getAttribute("aria-controls");
      panel.classList.toggle("is-active", active);
      panel.hidden = !active;
    });
    if(tab.id === "tab-how"){
      document.querySelector(".product-ecs-video")?.play().catch(()=>{});
    }
  };

  tabs.forEach(tab=>{
    tab.addEventListener("click", ()=>activateTab(tab));
    tab.addEventListener("keydown", e=>{
      const index = tabs.indexOf(tab);
      if(e.key === "ArrowRight"){
        e.preventDefault();
        activateTab(tabs[(index + 1) % tabs.length]);
        tabs[(index + 1) % tabs.length].focus();
      }
      if(e.key === "ArrowLeft"){
        e.preventDefault();
        activateTab(tabs[(index - 1 + tabs.length) % tabs.length]);
        tabs[(index - 1 + tabs.length) % tabs.length].focus();
      }
    });
  });

  const productEcsVideo = document.querySelector(".product-ecs-video");
  const ecsPanel = document.getElementById("panel-how");

  if(productEcsVideo && ecsPanel){
    const ecsObserver = new IntersectionObserver((entries)=>{
      entries.forEach(entry=>{
        if(entry.isIntersecting && !ecsPanel.hidden){
          productEcsVideo.play().catch(()=>{});
        }else{
          productEcsVideo.pause();
          productEcsVideo.currentTime = 0;
        }
      });
    },{threshold: 0.35});
    ecsObserver.observe(ecsPanel);
  }
})();
