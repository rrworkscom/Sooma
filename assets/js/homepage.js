const navToggle = document.querySelector('.nav-toggle');
const mainNav = document.querySelector('.main-nav');

if (navToggle && mainNav) {
  navToggle.addEventListener('click', () => {
    const isOpen = mainNav.classList.toggle('is-open');
    navToggle.setAttribute('aria-expanded', String(isOpen));
  });

  mainNav.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      mainNav.classList.remove('is-open');
      navToggle.setAttribute('aria-expanded', 'false');
    });
  });
}

const sections = document.querySelectorAll(
  '#home, #about, #classes, #offers, #timetable, #contact'
);

const navLinks = document.querySelectorAll('.main-nav a');

function updateActiveNav() {
  let currentSection = 'home';

  sections.forEach(section => {
    if (window.scrollY >= section.offsetTop - 150) {
      currentSection = section.id;
    }
  });

  navLinks.forEach(link => {
    const isActive = link.getAttribute('href') === `#${currentSection}`;

    link.classList.toggle('active', isActive);

    if (isActive) {
      link.setAttribute('aria-current', 'location');
    } else {
      link.removeAttribute('aria-current');
    }
  });
}

if (sections.length && navLinks.length) {
  window.addEventListener('scroll', updateActiveNav);
  updateActiveNav();
}

const gallery = document.querySelector('.studio-gallery');

if (gallery) {
  const prev = gallery.querySelector('.gallery-prev');
  const next = gallery.querySelector('.gallery-next');
  const main = gallery.querySelector('.gallery-main');
  const left = gallery.querySelector('.gallery-left');
  const right = gallery.querySelector('.gallery-right');

  const images = [
  '../assets/images/gallery-main.png',
  '../assets/images/gallery-left.png',
  '../assets/images/gallery-right.png'
];

  let current = 0;

  function renderGallery() {
    main.src = images[current];
    left.src = images[(current + images.length - 1) % images.length];
    right.src = images[(current + 1) % images.length];
  }

  prev?.addEventListener('click', () => {
    current = (current - 1 + images.length) % images.length;
    renderGallery();
  });

  next?.addEventListener('click', () => {
    current = (current + 1) % images.length;
    renderGallery();
  });
}
