/**
 * Public website interactions.
 *
 * Turbo Drive swaps the page body without a full reload, so everything here is
 * re-initialised on `turbo:load`. Per-page listeners are attached through one
 * AbortController per boot, which keeps them from stacking up across visits.
 */
(() => {
  let controller = null;

  const boot = () => {
    if (controller) controller.abort();
    controller = new AbortController();
    const { signal } = controller;
    const body = document.body;

    // Keep the sticky navigation outside the <header> so position:sticky works.
    const header = document.querySelector('header');
    const nav = document.querySelector('.main-nav');
    if (header && nav && nav.parentElement === header) {
      header.after(nav);
    }

    const drawer = document.querySelector('.drawer');
    const overlay = document.querySelector('.drawer-overlay');
    const menuBtn = document.querySelector('.menu-btn');
    const closeBtn = document.querySelector('.drawer-close');

    const openDrawer = () => {
      if (!drawer) return;
      drawer.classList.add('open');
      overlay?.classList.add('open');
      body.classList.add('drawer-open');
      drawer.setAttribute('aria-hidden', 'false');
      menuBtn?.setAttribute('aria-expanded', 'true');
    };

    const closeDrawer = () => {
      if (!drawer) return;
      drawer.classList.remove('open');
      overlay?.classList.remove('open');
      body.classList.remove('drawer-open');
      drawer.setAttribute('aria-hidden', 'true');
      menuBtn?.setAttribute('aria-expanded', 'false');
    };

    menuBtn?.addEventListener('click', openDrawer, { signal });
    closeBtn?.addEventListener('click', closeDrawer, { signal });
    overlay?.addEventListener('click', closeDrawer, { signal });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDrawer(); }, { signal });

    // Drawer accordion: first tap expands a section, the second follows the link.
    drawer?.querySelectorAll('.drawer-nav .drawer-group > a').forEach((link) => {
      link.addEventListener('click', (event) => {
        const group = link.parentElement;
        const hasSubmenu = !!group?.querySelector('.drawer-submenu a');

        if (!hasSubmenu || group.classList.contains('open')) {
          // Nothing to expand, or the section is already open: follow the link.
          closeDrawer();
          return;
        }

        event.preventDefault();
        event.stopPropagation();

        drawer.querySelectorAll('.drawer-group.open').forEach((other) => {
          if (other !== group) other.classList.remove('open');
        });

        group.classList.add('open');
      }, { signal });
    });

    // Leaf links close the drawer before navigating.
    drawer?.querySelectorAll('.drawer-nav .drawer-submenu a').forEach((link) => {
      link.addEventListener('click', closeDrawer, { signal });
    });

    const syncNav = () => {
      if (!nav) return;
      const mobile = window.innerWidth <= 900;
      nav.classList.toggle('scrolled', mobile || window.scrollY > 40);
    };
    window.addEventListener('scroll', syncNav, { passive: true, signal });
    window.addEventListener('resize', syncNav, { signal });
    syncNav();

    // Highlight the link for the current page (works for extension-less URLs).
    const here = location.pathname.replace(/\/+$/, '');
    document.querySelectorAll('.nav-links a, .drawer-nav a, .side-menu a').forEach((a) => {
      const href = (a.getAttribute('href') || '').split(/[?#]/)[0];
      if (!href) return;
      const path = new URL(href, location.origin).pathname.replace(/\/+$/, '');
      a.classList.toggle('active', path !== '' && path === here);
    });

    // Reveal-on-scroll, re-observed after each Turbo visit.
    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) entry.target.classList.add('show');
        });
      }, { threshold: 0.08 });

      document.querySelectorAll('.reveal:not(.show)').forEach((el) => observer.observe(el));
      signal.addEventListener('abort', () => observer.disconnect(), { once: true });
    }

    // Hero slider.
    const slides = [...document.querySelectorAll('.hero-slide')];
    const dots = [...document.querySelectorAll('.hero-dot')];

    if (slides.length) {
      let index = Math.max(0, slides.findIndex((slide) => slide.classList.contains('active')));

      const showSlide = (next) => {
        slides.forEach((slide, i) => slide.classList.toggle('active', i === next));
        dots.forEach((dot, i) => dot.classList.toggle('active', i === next));
        index = next;
      };

      dots.forEach((dot, i) => dot.addEventListener('click', () => showSlide(i), { signal }));

      if (slides.length > 1) {
        const timer = window.setInterval(() => showSlide((index + 1) % slides.length), 5000);
        signal.addEventListener('abort', () => window.clearInterval(timer), { once: true });
      }
    }

    // Administration message slider.
    const messageSlider = document.querySelector('.message-slider');
    if (messageSlider) {
      const track = messageSlider.querySelector('.message-track');
      const cards = [...messageSlider.querySelectorAll('.message-card')];
      const prevBtn = messageSlider.closest('section')?.querySelector('.message-prev');
      const nextBtn = messageSlider.closest('section')?.querySelector('.message-next');
      let mIndex = 0;

      const visibleCount = () => {
        const w = window.innerWidth;
        const d = messageSlider.dataset;
        if (w <= 760) return parseInt(d.visibleMobile || '1', 10);
        if (w <= 900) return parseInt(d.visibleTablet || '2', 10);
        return parseInt(d.visibleDesktop || '2', 10);
      };

      const gapSize = () => {
        const styles = getComputedStyle(track);
        return parseFloat(styles.columnGap || styles.gap || '18') || 18;
      };

      const renderMessages = () => {
        if (!track || !cards.length) return;
        const visible = Math.max(1, Math.min(visibleCount(), cards.length));
        const maxIndex = Math.max(0, cards.length - visible);
        mIndex = Math.max(0, Math.min(mIndex, maxIndex));
        messageSlider.style.setProperty('--message-visible', visible);
        const gap = gapSize();
        const cardWidth = (messageSlider.clientWidth - ((visible - 1) * gap)) / visible;
        track.style.transform = `translate3d(-${mIndex * (cardWidth + gap)}px, 0, 0)`;
        if (prevBtn) prevBtn.disabled = mIndex <= 0;
        if (nextBtn) nextBtn.disabled = mIndex >= maxIndex;
        const navEl = messageSlider.closest('section')?.querySelector('.message-nav');
        if (navEl) navEl.style.display = maxIndex > 0 ? '' : 'none';
      };

      prevBtn?.addEventListener('click', () => { mIndex = Math.max(0, mIndex - 1); renderMessages(); }, { signal });
      nextBtn?.addEventListener('click', () => {
        const visible = Math.max(1, Math.min(visibleCount(), cards.length));
        mIndex = Math.min(cards.length - visible, mIndex + 1);
        renderMessages();
      }, { signal });
      window.addEventListener('resize', renderMessages, { signal });
      renderMessages();
    }

    // Password reveal toggles.
    document.querySelectorAll('.show-pass').forEach((btn) => {
      btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.target);
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        btn.textContent = input.type === 'password' ? 'দেখুন' : 'লুকান';
      }, { signal });
    });

    // Image gallery lightbox.
    const gallery = document.getElementById('gallery-lightbox');
    if (gallery) {
      const items = [...document.querySelectorAll('[data-gallery-image]')];
      const image = document.getElementById('gallery-lightbox-image');
      const caption = document.getElementById('gallery-lightbox-caption');

      if (items.length && image && caption) {
        let active = 0;
        const show = (index) => {
          active = (index + items.length) % items.length;
          const item = items[active];
          image.src = item.dataset.galleryImage;
          image.alt = item.dataset.galleryTitle || '';
          caption.textContent = [item.dataset.galleryTitle, item.dataset.galleryCaption].filter(Boolean).join(' · ');
          if (!gallery.open) gallery.showModal();
        };

        items.forEach((item, index) => item.addEventListener('click', () => show(index), { signal }));
        document.querySelector('[data-gallery-prev]')?.addEventListener('click', () => show(active - 1), { signal });
        document.querySelector('[data-gallery-next]')?.addEventListener('click', () => show(active + 1), { signal });
        document.querySelector('[data-gallery-close]')?.addEventListener('click', () => gallery.close(), { signal });
        gallery.addEventListener('click', (event) => { if (event.target === gallery) gallery.close(); }, { signal });
        gallery.addEventListener('keydown', (event) => {
          if (event.key === 'ArrowLeft') show(active - 1);
          if (event.key === 'ArrowRight') show(active + 1);
        }, { signal });
      }
    }

    // Homepage gallery lightbox.
    const homeGallery = document.getElementById('home-gallery-lightbox');
    if (homeGallery) {
      const items = [...document.querySelectorAll('[data-home-gallery-image]')];
      const image = document.getElementById('home-gallery-image');
      const caption = document.getElementById('home-gallery-caption');

      if (items.length && image && caption) {
        let active = 0;
        const show = (index) => {
          active = (index + items.length) % items.length;
          const item = items[active];
          image.src = item.dataset.homeGalleryImage;
          image.alt = item.dataset.homeGalleryTitle || '';
          caption.textContent = item.dataset.homeGalleryTitle || '';
          if (!homeGallery.open) homeGallery.showModal();
        };

        items.forEach((item, index) => item.addEventListener('click', () => show(index), { signal }));
        document.querySelector('[data-home-gallery-prev]')?.addEventListener('click', () => show(active - 1), { signal });
        document.querySelector('[data-home-gallery-next]')?.addEventListener('click', () => show(active + 1), { signal });
        document.querySelector('[data-home-gallery-close]')?.addEventListener('click', () => homeGallery.close(), { signal });
        homeGallery.addEventListener('click', (event) => { if (event.target === homeGallery) homeGallery.close(); }, { signal });
        homeGallery.addEventListener('keydown', (event) => {
          if (event.key === 'ArrowLeft') show(active - 1);
          if (event.key === 'ArrowRight') show(active + 1);
        }, { signal });
      }
    }

    // Video gallery lightbox.
    const videoDialog = document.getElementById('video-lightbox');
    if (videoDialog) {
      const frame = document.getElementById('video-frame');

      if (frame) {
        const openVideo = (src) => {
          frame.src = src;
          if (typeof videoDialog.showModal === 'function') videoDialog.showModal();
          else videoDialog.setAttribute('open', 'open');
        };
        const closeVideo = () => {
          videoDialog.close?.();
          frame.src = '';
        };

        document.querySelectorAll('[data-video-embed]').forEach((card) => {
          card.addEventListener('click', () => {
            const embed = card.dataset.videoEmbed;
            if (embed) { openVideo(embed); return; }
            if (card.dataset.videoUrl) window.open(card.dataset.videoUrl, '_blank', 'noopener');
          }, { signal });
        });

        document.getElementById('video-close')?.addEventListener('click', closeVideo, { signal });
        videoDialog.addEventListener('close', () => { frame.src = ''; }, { signal });
        videoDialog.addEventListener('click', (event) => { if (event.target === videoDialog) closeVideo(); }, { signal });
      }
    }
  };

  document.addEventListener('turbo:load', boot);
  document.addEventListener('DOMContentLoaded', boot);

  // Turbo may restore a cached page before DOMContentLoaded fires.
  if (document.readyState !== 'loading') boot();
})();
