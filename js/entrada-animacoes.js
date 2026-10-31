document.addEventListener('DOMContentLoaded', () => {
  if (!window.gsap || !window.ScrollTrigger) return;

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  gsap.registerPlugin(ScrollTrigger);

  const fadeIn = {
    autoAlpha: 0,
    y: 18
  };

  const revealOnScroll = (trigger, targets) => {
    const elements = gsap.utils.toArray(targets);
    if (!elements.length) return;

    gsap.fromTo(elements, fadeIn, {
      autoAlpha: 1,
      y: 0,
      duration: 0.55,
      stagger: 0.07,
      ease: 'power2.out',
      clearProps: 'opacity,visibility,transform',
      scrollTrigger: {
        trigger,
        start: 'top 84%',
        once: true
      }
    });
  };

  if (document.body.classList.contains('home-page')) {
    gsap.fromTo('.home-navbar', fadeIn, {
      autoAlpha: 1,
      y: 0,
      duration: 0.45,
      ease: 'power2.out',
      clearProps: 'opacity,visibility,transform'
    });

    gsap.fromTo('.hero-text-content > *', fadeIn, {
      autoAlpha: 1,
      y: 0,
      duration: 0.55,
      stagger: 0.07,
      delay: 0.08,
      ease: 'power2.out',
      clearProps: 'opacity,visibility,transform'
    });

    revealOnScroll('#como-funciona', '.needs-section .section-container-centered > *, .need-card-item');
    revealOnScroll('#equipe', '.team-section .section-container-centered > *, .team-card-item');
    revealOnScroll('#sobre', '.about-section > *');
    revealOnScroll('#contato', '.contact-card-box > *');
    revealOnScroll('.site-footer', '.site-footer > *');
    return;
  }

  const isLogin = document.body.classList.contains('login-page');
  const isCadastro = document.body.classList.contains('cadastro-page');
  if (!isLogin && !isCadastro) return;

  const columns = isLogin
    ? '.login-illustration-column, .login-card-column'
    : '.cadastro-illustration-column, .cadastro-card-column';

  gsap.fromTo(columns, fadeIn, {
    autoAlpha: 1,
    y: 0,
    duration: 0.55,
    stagger: 0.1,
    ease: 'power2.out',
    clearProps: 'opacity,visibility,transform'
  });

  const formElements = isLogin
    ? '#form-login > .form-field-item, #form-login > .login-options-row, #form-login > .btn-submit-login, #form-login > .btn-admin-test, #form-login > .login-card-footer, #form-login > .login-terms-row, #form-login > .login-divider, #form-login > .btn-google-login'
    : '#form-cadastro > .form-field-item, #form-cadastro > .form-fields-row-2col, #form-cadastro > .pcd-selection-section, #form-cadastro > .terms-checkbox-wrap, #form-cadastro > .btn-submit-cadastro, #form-cadastro > .cadastro-card-footer';

  gsap.fromTo(formElements, fadeIn, {
    autoAlpha: 1,
    y: 0,
    duration: 0.45,
    stagger: 0.045,
    delay: 0.12,
    ease: 'power2.out',
    clearProps: 'opacity,visibility,transform'
  });
});
