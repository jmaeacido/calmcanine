const reveals = document.querySelectorAll(".reveal");
const observer = new IntersectionObserver((entries)=>{
  entries.forEach((entry, i)=>{
    if(entry.isIntersecting){
      setTimeout(()=>entry.target.classList.add("visible"), i*55);
      observer.unobserve(entry.target);
    }
  });
},{threshold:.12});
reveals.forEach(el=>observer.observe(el));

const finePointer = window.matchMedia("(hover: hover) and (pointer: fine)");
const mobileLayout = window.matchMedia("(max-width: 1000px)");
const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

const heroBgVideo = document.querySelector(".hero-bg-video");
if(heroBgVideo){
  const syncHeroBgVideo = ()=>{
    if(reducedMotion.matches){
      heroBgVideo.pause();
      return;
    }
    heroBgVideo.play().catch(()=>{});
  };
  syncHeroBgVideo();
  heroBgVideo.addEventListener("loadedmetadata", syncHeroBgVideo);
  reducedMotion.addEventListener("change", syncHeroBgVideo);
}

const stage = document.querySelector("[data-tilt]");
if(stage && finePointer.matches){
  stage.addEventListener("mousemove", e=>{
    const r = stage.getBoundingClientRect();
    const x = (e.clientX-r.left)/r.width-.5;
    const y = (e.clientY-r.top)/r.height-.5;
    stage.style.transform = `rotateY(${x*8}deg) rotateX(${-y*8}deg)`;
  });
  stage.addEventListener("mouseleave",()=>stage.style.transform="");
}

const glow = document.querySelector(".cursor-glow");
if(glow && finePointer.matches){
  window.addEventListener("pointermove", e=>{
    glow.style.left=e.clientX+"px";
    glow.style.top=e.clientY+"px";
  });
}

const productStage = document.querySelector(".product-stage");
const productShowcase = document.querySelector(".product-showcase");
const videoOrbit = document.querySelector(".video-orbit");
const momentsDots = document.querySelector(".moments-dots");
const momentDotEls = momentsDots ? [...momentsDots.querySelectorAll("span")] : [];
const clips = productStage ? [...productStage.querySelectorAll(".dog-clip")] : [];
const widgets = productStage ? [...productStage.querySelectorAll(".dog-widget")] : [];

const playClips = ()=>clips.forEach(v=>v.play().catch(()=>{}));
const pauseClips = ()=>clips.forEach(v=>{v.pause();v.currentTime=0});

const setActiveMoment = (activeWidget)=>{
  const activeIndex = widgets.indexOf(activeWidget);
  widgets.forEach(widget=>{
    const isCenter = widget === activeWidget;
    widget.classList.toggle("is-centered", isCenter);
    const video = widget.querySelector(".dog-clip");
    if(!video) return;
    if(isCenter) video.play().catch(()=>{});
    else{
      video.pause();
      video.currentTime = 0;
    }
  });
  momentDotEls.forEach((dot, index)=>{
    dot.classList.toggle("is-active", index === activeIndex);
  });
};

if(productStage){
  const setOrbitActive = (active)=>{
    if(mobileLayout.matches) return;
    productStage.classList.toggle("is-active", active);
  };

  productStage.addEventListener("mouseenter", ()=>{
    if(finePointer.matches){
      setOrbitActive(true);
      playClips();
    }
  });
  productStage.addEventListener("focusin", ()=>{
    if(finePointer.matches){
      setOrbitActive(true);
      playClips();
    }
  });
  productStage.addEventListener("mouseleave", ()=>{
    if(finePointer.matches){
      setOrbitActive(false);
      pauseClips();
    }
  });
  productStage.addEventListener("focusout", (e)=>{
    if(finePointer.matches && !productStage.contains(e.relatedTarget)){
      setOrbitActive(false);
      pauseClips();
    }
  });
}

if(productShowcase){
  productShowcase.addEventListener("click", ()=>{
    if(mobileLayout.matches) productStage.classList.toggle("journey-active");
  });
}

let carouselObserver;
const syncCarouselVideos = ()=>{
  widgets.forEach(widget=>widget.classList.remove("is-centered"));
  carouselObserver?.disconnect();

  if(!mobileLayout.matches || !videoOrbit){
    pauseClips();
    return;
  }

  productStage?.classList.remove("journey-active", "is-active");

  carouselObserver = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      if(entry.isIntersecting && entry.intersectionRatio >= .72){
        setActiveMoment(entry.target);
      }
    });
  },{root:videoOrbit,threshold:[0,.72,1]});

  widgets.forEach(widget=>carouselObserver.observe(widget));

  if(widgets[0]) setActiveMoment(widgets[0]);
};

syncCarouselVideos();
mobileLayout.addEventListener("change", ()=>{
  stage && (stage.style.transform = "");
  syncCarouselVideos();
});

videoOrbit?.addEventListener("scroll", ()=>{
  if(!mobileLayout.matches) return;
  window.requestAnimationFrame(()=>{
    let best = null;
    let bestRatio = 0;
    widgets.forEach(widget=>{
      const rect = widget.getBoundingClientRect();
      const orbitRect = videoOrbit.getBoundingClientRect();
      const visible = Math.max(0, Math.min(rect.right, orbitRect.right) - Math.max(rect.left, orbitRect.left));
      const ratio = visible / rect.width;
      if(ratio > bestRatio){
        bestRatio = ratio;
        best = widget;
      }
    });
    if(best && bestRatio > .7) setActiveMoment(best);
  });
},{passive:true});

const ecsSection = document.querySelector(".ecs-section");
const ecsVideo = document.querySelector(".ecs-video");

if(ecsSection && ecsVideo){
  const ecsObserver = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      if(entry.isIntersecting){
        ecsVideo.play().catch(()=>{});
      }else{
        ecsVideo.pause();
        ecsVideo.currentTime = 0;
      }
    });
  },{threshold: 0.35});

  ecsObserver.observe(ecsSection);
}

const gallerySection = document.querySelector(".gallery-section");
const galleryMoments = gallerySection ? [...gallerySection.querySelectorAll("[data-gallery-moment]")] : [];

const playGalleryClip = (clip)=>{
  if(reducedMotion.matches) return;
  clip.play().catch(()=>{});
};

const pauseGalleryClip = (clip)=>{
  clip.pause();
  clip.currentTime = 0;
};

if(galleryMoments.length){
  galleryMoments.forEach(card=>card.setAttribute("tabindex", "0"));

  if(finePointer.matches){
    galleryMoments.forEach(card=>{
      const clip = card.querySelector(".gallery-clip");
      if(!clip) return;
      card.addEventListener("mouseenter", ()=>playGalleryClip(clip));
      card.addEventListener("mouseleave", ()=>pauseGalleryClip(clip));
      card.addEventListener("focusin", ()=>playGalleryClip(clip));
      card.addEventListener("focusout", ()=>pauseGalleryClip(clip));
    });
  }else{
    const galleryObserver = new IntersectionObserver((entries)=>{
      entries.forEach(entry=>{
        const clip = entry.target.querySelector(".gallery-clip");
        if(!clip) return;
        if(entry.isIntersecting && entry.intersectionRatio >= 0.45){
          playGalleryClip(clip);
        }else{
          pauseGalleryClip(clip);
        }
      });
    },{threshold: [0, 0.45, 0.7]});

    galleryMoments.forEach(card=>{
      galleryObserver.observe(card);
    });
  }
}

const shopVisual = document.querySelector("[data-shop-tilt]");
const shopSection = document.querySelector(".shop-section");

if(shopVisual && finePointer.matches){
  shopVisual.addEventListener("mousemove", e=>{
    const r = shopVisual.getBoundingClientRect();
    const x = (e.clientX - r.left) / r.width - 0.5;
    const y = (e.clientY - r.top) / r.height - 0.5;
    const stage = shopVisual.querySelector(".shop-product-stage");
    if(stage){
      stage.style.transform = `rotateY(${x * 10}deg) rotateX(${-y * 8}deg) translateZ(12px)`;
    }
  });
  shopVisual.addEventListener("mouseleave", ()=>{
    const stage = shopVisual.querySelector(".shop-product-stage");
    if(stage) stage.style.transform = "";
  });
}

if(shopSection && shopVisual && !reducedMotion.matches && !mobileLayout.matches){
  const shopParallax = ()=>{
    const rect = shopSection.getBoundingClientRect();
    const progress = 1 - Math.min(1, Math.max(0, (rect.top + rect.height * 0.5) / (window.innerHeight + rect.height * 0.5)));
    shopVisual.style.transform = `translateY(${progress * -18}px)`;
  };
  shopParallax();
  window.addEventListener("scroll", ()=>window.requestAnimationFrame(shopParallax), {passive: true});
  mobileLayout.addEventListener("change", ()=>{
    if(mobileLayout.matches) shopVisual.style.transform = "";
  });
}
