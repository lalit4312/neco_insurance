(() => {
  const slider = document.querySelector('[data-banner-slider]');
  if (!slider) return;

  const track = slider.querySelector('[data-banner-track]');
  const slides = Array.from(slider.querySelectorAll('.banner-slide'));
  const dots = Array.from(slider.querySelectorAll('[data-banner-to]'));
  const previous = slider.querySelector('[data-banner-previous]');
  const next = slider.querySelector('[data-banner-next]');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let activeIndex = 0;
  let timer;

  function showSlide(index) {
    activeIndex = (index + slides.length) % slides.length;
    track.style.transform = `translateX(-${activeIndex * 100}%)`;
    slides.forEach((slide, slideIndex) => {
      slide.setAttribute('aria-hidden', String(slideIndex !== activeIndex));
    });
    dots.forEach((dot, dotIndex) => {
      dot.setAttribute('aria-current', String(dotIndex === activeIndex));
    });
  }

  function stopAutoplay() {
    window.clearInterval(timer);
  }

  function startAutoplay() {
    stopAutoplay();
    if (!reducedMotion) {
      timer = window.setInterval(() => showSlide(activeIndex + 1), 6000);
    }
  }

  previous.addEventListener('click', () => {
    showSlide(activeIndex - 1);
    startAutoplay();
  });
  next.addEventListener('click', () => {
    showSlide(activeIndex + 1);
    startAutoplay();
  });
  dots.forEach((dot) => {
    dot.addEventListener('click', () => {
      showSlide(Number(dot.dataset.bannerTo));
      startAutoplay();
    });
  });

  slider.addEventListener('focusin', stopAutoplay);
  slider.addEventListener('focusout', (event) => {
    if (!slider.contains(event.relatedTarget)) startAutoplay();
  });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stopAutoplay();
    else startAutoplay();
  });

  showSlide(0);
  startAutoplay();
})();
