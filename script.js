// NAV SCROLL
const navHeader = document.getElementById('nav-header');
window.addEventListener('scroll', () => {
  navHeader.classList.toggle('scrolled', window.scrollY > 60);
});

// MOBILE NAV
const navToggle = document.getElementById('nav-toggle');
const navLinks = document.getElementById('nav-links');
navToggle.addEventListener('click', () => {
  navToggle.classList.toggle('active');
  navLinks.classList.toggle('open');
});
navLinks.querySelectorAll('a').forEach(link => {
  link.addEventListener('click', () => {
    navToggle.classList.remove('active');
    navLinks.classList.remove('open');
  });
});

// REVEAL ON SCROLL
const reveals = document.querySelectorAll('.reveal');
const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry, i) => {
    if (entry.isIntersecting) {
      setTimeout(() => entry.target.classList.add('visible'), i * 80);
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });
reveals.forEach(el => observer.observe(el));

// REVIEWS SLIDER
const cards = document.querySelectorAll('.review-card');
const dots = document.querySelectorAll('.dot');
let current = 0;

function goToReview(idx) {
  cards[current].classList.remove('active');
  dots[current].classList.remove('active');
  current = (idx + cards.length) % cards.length;
  cards[current].classList.add('active');
  dots[current].classList.add('active');
}

document.getElementById('prev-review').addEventListener('click', () => goToReview(current - 1));
document.getElementById('next-review').addEventListener('click', () => goToReview(current + 1));
dots.forEach((dot, i) => dot.addEventListener('click', () => goToReview(i)));

// Auto-advance
setInterval(() => goToReview(current + 1), 6000);

// PORTFOLIO FILTER
const filterBtns = document.querySelectorAll('.filter-btn');
const portfolioItems = document.querySelectorAll('.portfolio-item');

function applyFilter(filter) {
  portfolioItems.forEach(item => {
    if (item.dataset.category === filter) {
      item.style.display = '';
      item.style.opacity = '1';
    } else {
      item.style.opacity = '0';
      item.style.display = 'none';
    }
  });
}

// Initialize with the first active filter
const initialFilter = document.querySelector('.filter-btn.active');
if (initialFilter) applyFilter(initialFilter.dataset.filter);

filterBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    filterBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyFilter(btn.dataset.filter);
  });
});

// CONTACT FORM
const form = document.getElementById('contact-form');
const formSuccess = document.getElementById('form-success');

form.addEventListener('submit', (e) => {
  e.preventDefault();
  const name = form.querySelector('#name').value.trim();
  const phone = form.querySelector('#phone').value.trim();
  if (!name || !phone) {
    const missing = !name ? form.querySelector('#name') : form.querySelector('#phone');
    missing.style.borderColor = 'var(--red)';
    missing.focus();
    setTimeout(() => missing.style.borderColor = '', 2000);
    return;
  }
  form.style.display = 'none';
  formSuccess.classList.add('visible');
});

// PHONE MASK
const phoneInput = document.getElementById('phone');
phoneInput.addEventListener('input', (e) => {
  let v = e.target.value.replace(/\D/g, '');
  if (v.startsWith('8')) v = '7' + v.slice(1);
  if (v.startsWith('7')) {
    v = '+7 (' + v.slice(1, 4) + ') ' + v.slice(4, 7) + '-' + v.slice(7, 9) + '-' + v.slice(9, 11);
  }
  e.target.value = v.trim().replace(/[-\s()]+$/, '');
});

// SMOOTH ACTIVE NAV LINKS
const sections = document.querySelectorAll('section[id]');
window.addEventListener('scroll', () => {
  const scrollPos = window.scrollY + 120;
  sections.forEach(section => {
    if (scrollPos >= section.offsetTop && scrollPos < section.offsetTop + section.offsetHeight) {
      document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active-link');
        if (link.getAttribute('href') === '#' + section.id) {
          link.classList.add('active-link');
        }
      });
    }
  });
}, { passive: true });
