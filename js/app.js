document.addEventListener('DOMContentLoaded', () => {

  // MENU MOBILE
  const sidebar    = document.getElementById('sidebar');
  const menuToggle = document.getElementById('menu-toggle');

  function checkMobile() {
    if (window.innerWidth <= 768) {
      if (menuToggle) menuToggle.style.display = 'flex';
    } else {
      if (menuToggle) menuToggle.style.display = 'none';
      if (sidebar)    sidebar.classList.remove('open');
    }
  }
  if (menuToggle) {
    menuToggle.addEventListener('click', () => sidebar?.classList.toggle('open'));
  }
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 && sidebar?.classList.contains('open')) {
      if (!sidebar.contains(e.target) && e.target !== menuToggle) {
        sidebar.classList.remove('open');
      }
    }
  });
  window.addEventListener('resize', checkMobile);
  checkMobile();

  // FERMETURE AUTO DES ALERTES
  document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity 0.5s, transform 0.5s';
      alert.style.opacity    = '0';
      alert.style.transform  = 'translateY(-8px)';
      setTimeout(() => alert.remove(), 500);
    }, 4000);
  });

  // MODAL GLOBAL
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
  });

  // ANIMATION DES CARTES
  // Uniquement sur les pages sans filtres actifs dans l'URL
  // Cela évite le clignotement à chaque changement de filtre ou reset
  const params = new URLSearchParams(window.location.search);
  const filtresActifs = params.has('q') || params.has('categorie') || params.has('piece')
                     || params.has('etat') || params.has('marque') || params.has('type')
                     || params.has('cat') || params.has('niveau');

  if (!filtresActifs) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity   = '1';
          entry.target.style.transform = 'translateY(0)';
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.05 });

    document.querySelectorAll('.device-card, .stat-card').forEach((el, i) => {
      el.style.opacity   = '0';
      el.style.transform = 'translateY(14px)';
      el.style.transition = `opacity 0.3s ease ${i * 0.035}s, transform 0.3s ease ${i * 0.035}s`;
      observer.observe(el);
    });
  }

  // TOPBAR SHADOW ON SCROLL
  const topbar = document.querySelector('.topbar');
  if (topbar) {
    window.addEventListener('scroll', () => {
      topbar.style.boxShadow = window.scrollY > 10 ? '0 4px 20px rgba(0,0,0,0.3)' : 'none';
    }, { passive: true });
  }

  // PROGRESS BAR ANIMATION
  document.querySelectorAll('.progress-fill').forEach(bar => {
    const target = bar.style.width;
    bar.style.width = '0%';
    setTimeout(() => {
      bar.style.transition = 'width 0.8s ease';
      bar.style.width = target;
    }, 150);
  });

});

// TOAST GLOBAL
function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.className = `alert alert-${type}`;
  toast.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:999;max-width:320px';
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}
