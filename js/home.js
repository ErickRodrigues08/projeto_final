/**
 * Passe Livre — Lógica da Home Principal (Landing Page)
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Rolagem Suave para Âncoras do Menu
  const navLinks = document.querySelectorAll('.home-nav-links a[href^="#"]');
  navLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const targetId = link.getAttribute('href').substring(1);
      const targetElement = document.getElementById(targetId);
      if (targetElement) {
        targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

  const navSections = [...new Set([...navLinks]
    .map(link => document.getElementById(link.getAttribute('href').substring(1)))
    .filter(Boolean))];

  if ('IntersectionObserver' in window) {
    const sectionVisibility = new Map();
    const sectionObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        sectionVisibility.set(entry.target, entry.isIntersecting);
      });

      const marker = window.innerHeight * 0.25;
      const visibleSections = navSections.filter(section => sectionVisibility.get(section));
      const currentSection = visibleSections
        .filter(section => {
          const bounds = section.getBoundingClientRect();
          return bounds.top <= marker && bounds.bottom > marker;
        })
        .sort((first, second) => second.getBoundingClientRect().top - first.getBoundingClientRect().top)[0]
        || visibleSections[0];

      navLinks.forEach(link => {
        const isCurrent = currentSection
          && link.getAttribute('href') === `#${currentSection.id}`;
        link.classList.toggle('active', Boolean(isCurrent));
        if (isCurrent) {
          link.setAttribute('aria-current', 'location');
        } else {
          link.removeAttribute('aria-current');
        }
      });
    }, {
      rootMargin: '-20% 0px -70% 0px',
      threshold: 0
    });

    navSections.forEach(section => sectionObserver.observe(section));
  }

  // 2. Formulário de Contato — Gravação no MySQL via api/contato.php
  const contactForm = document.getElementById('contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const nameInput    = document.getElementById('contact-name');
      const emailInput   = document.getElementById('contact-email');
      const messageInput = document.getElementById('contact-message');

      const name    = nameInput.value.trim();
      const email   = emailInput.value.trim();
      const message = messageInput.value.trim();

      if (!name || !email || !message) {
        showToast('Por favor, preencha todos os campos do formulário.');
        return;
      }

      try {
        const resp = await fetch('api/contato.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ name, email, message })
        });
        const res = await resp.json();
        if (res.success) {
          showToast(res.message || 'Mensagem gravada no banco com sucesso! A equipe entrará em contato.');
          contactForm.reset();
          return;
        }
      } catch (err) {
        console.warn('[Contato] Erro ao enviar para API:', err);
      }

      // === INTEGRAÇÃO SQL: registra contato no log de auditoria ===
      if (window.PLDBAuditLog) {
        await window.PLDBAuditLog(
          'sistema',
          'Formulário de Contato Enviado',
          `Mensagem recebida de "${name}" (${email})`,
          'baixo'
        );
      }

      showToast('Mensagem enviada com sucesso! A equipe do SENAI Suzano entrará em contato.');
      contactForm.reset();
    });
  }

  // 3. Sistema de Toast Feedback
  let toastTimer = null;
  function showToast(message) {
    const toast = document.getElementById('app-toast');
    const toastMsg = document.getElementById('toast-message');
    if (!toast || !toastMsg) return;

    toastMsg.textContent = message;
    toast.classList.add('visible');

    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toast.classList.remove('visible');
    }, 4000);
  }
});

// Menu mobile (hambúrguer) do cabeçalho
document.addEventListener('DOMContentLoaded', () => {
  const navToggle = document.getElementById('home-nav-toggle');
  const navLinks = document.getElementById('home-nav-links');
  if (!navToggle || !navLinks) return;

  navToggle.addEventListener('click', () => {
    const isOpen = navLinks.classList.toggle('is-open');
    navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });

  navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      navLinks.classList.remove('is-open');
      navToggle.setAttribute('aria-expanded', 'false');
    });
  });
});
