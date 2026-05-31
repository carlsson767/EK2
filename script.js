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
  document.body.classList.toggle('no-scroll');
});
navLinks.querySelectorAll('a').forEach(link => {
  link.addEventListener('click', () => {
    navToggle.classList.remove('active');
    navLinks.classList.remove('open');
    document.body.classList.remove('no-scroll');
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
async function loadReviews() {
  try {
    const response = await fetch('api_reviews.php');
    const result = await response.json();
    if (result.success) renderReviews(result.data);
  } catch (error) { console.error('Ошибка загрузки отзывов:', error); }
}

function renderReviews(reviews) {
  const slider = document.getElementById('reviews-slider');
  const dotsContainer = document.getElementById('review-dots');
  slider.innerHTML = '';
  dotsContainer.innerHTML = '';

  if (reviews.length === 0) {
    slider.innerHTML = '<p style="text-align: center; color: #888; padding: 2rem;">Отзывы скоро появятся...</p>';
    document.querySelector('.reviews-controls').style.display = 'none';
    return;
  }
  document.querySelector('.reviews-controls').style.display = 'flex';

  reviews.forEach((r, idx) => {
    const card = document.createElement('div');
    card.className = `review-card ${idx === 0 ? 'active' : ''}`;
    card.innerHTML = `
      <div class="review-quote">"</div>
      <p class="review-text">${r.text}</p>
      <div class="review-author">
        <div class="review-avatar">${r.avatar_text || r.author_name.substring(0,2).toUpperCase()}</div>
        <div>
          <strong>${r.author_name}</strong>
          <span>${r.author_role || ''}</span>
        </div>
      </div>
    `;
    slider.appendChild(card);
    
    const dot = document.createElement('span');
    dot.className = `dot ${idx === 0 ? 'active' : ''}`;
    dotsContainer.appendChild(dot);
  });

  const cards = slider.querySelectorAll('.review-card');
  const dots = dotsContainer.querySelectorAll('.dot');
  let current = 0;
  let reviewInterval;

  function goToReview(idx) {
    cards[current].classList.remove('active');
    dots[current].classList.remove('active');
    current = (idx + cards.length) % cards.length;
    cards[current].classList.add('active');
    dots[current].classList.add('active');
  }
  function resetInterval() {
    clearInterval(reviewInterval);
    reviewInterval = setInterval(() => goToReview(current + 1), 6000);
  }

  document.getElementById('prev-review').onclick = () => { goToReview(current - 1); resetInterval(); };
  document.getElementById('next-review').onclick = () => { goToReview(current + 1); resetInterval(); };
  dots.forEach((dot, i) => dot.onclick = () => { goToReview(i); resetInterval(); });
  resetInterval();
}

// PORTFOLIO FILTER
const filterBtns = document.querySelectorAll('.filter-btn');

function applyFilter(filter) {
  const portfolioItems = document.querySelectorAll('.portfolio-item');
  portfolioItems.forEach(item => {
    if (item.dataset.category === filter || filter === 'all') {
      item.style.display = '';
      item.style.opacity = '1';
    } else {
      item.style.opacity = '0';
      item.style.display = 'none';
    }
  });
}

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

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const nameInput = form.querySelector('#name');
  const phoneInput = form.querySelector('#phone');
  const privacyInput = form.querySelector('#privacy');
  const name = nameInput.value.trim();
  const phone = phoneInput.value.trim();

  if (!name || !phone || !privacyInput.checked) {
    if (!name || !phone) {
      const missing = !name ? nameInput : phoneInput;
      missing.style.borderColor = 'var(--red)';
      missing.focus();
      setTimeout(() => missing.style.borderColor = '', 2000);
    } else {
      // Если не стоит галочка — подсвечиваем текст красным на 2 секунды
      const privacyLabel = form.querySelector('label[for="privacy"]');
      privacyLabel.style.color = 'var(--red)';
      setTimeout(() => privacyLabel.style.color = '', 2000);
    }
    return;
  }

  // Блокируем кнопку на время отправки
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalBtnText = submitBtn.innerHTML;
  submitBtn.innerHTML = 'Отправка...';
  submitBtn.disabled = true;

  try {
    const response = await fetch('send.php', {
      method: 'POST',
      body: new FormData(form) // автоматически собирает все input внутри формы
    });
    const result = await response.json();
    
    if (result.success) {
      form.style.display = 'none';
      formSuccess.classList.add('visible');
    } else {
      alert('Ошибка: ' + (result.message || 'Не удалось отправить заявку'));
      submitBtn.innerHTML = originalBtnText;
      submitBtn.disabled = false;
    }
  } catch (error) {
    alert('Произошла ошибка при отправке. Пожалуйста, попробуйте позже.');
    submitBtn.innerHTML = originalBtnText;
    submitBtn.disabled = false;
  }
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

// DYNAMIC PORTFOLIO LOADING
async function loadPortfolio() {
  try {
    const response = await fetch('api_portfolio.php');
    const result = await response.json();
    
    if (result.success) {
      renderPortfolio(result.data);
    }
  } catch (error) {
    console.error('Ошибка загрузки портфолио:', error);
  }
}

function renderPortfolio(projects) {
  const grid = document.querySelector('.portfolio-grid');
  grid.innerHTML = '';
  
  if (projects.length === 0) {
    grid.innerHTML = '<p style="grid-column: 1/-1; color: #888; text-align: center;">Работы загружаются...</p>';
    return;
  }

  projects.forEach((p, index) => {
    const item = document.createElement('div');
    item.className = `portfolio-item reveal`;
    item.dataset.category = p.category;
    
    item.innerHTML = `
      <div class="portfolio-img" style="background-image: url('${p.cover_image}')"></div>
      <div class="portfolio-overlay">
        <span class="portfolio-tag">${p.category_name}</span>
        <h4>${p.title}</h4>
        <span class="portfolio-link">Смотреть проект →</span>
      </div>
    `;
    
    item.addEventListener('click', () => openModal(p));
    grid.appendChild(item);
    observer.observe(item); // Анимируем появление при скролле
  });
  
  // После отрисовки применяем текущий активный фильтр
  const activeBtn = document.querySelector('.filter-btn.active');
  if (activeBtn) applyFilter(activeBtn.dataset.filter);
}

// PORTFOLIO MODAL LOGIC
const modal = document.getElementById('portfolio-modal');
const modalClose = document.getElementById('modal-close');
const modalTitle = document.getElementById('modal-title');
const modalCategory = document.getElementById('modal-category');

function openModal(project) {
  modalTitle.innerText = project.title;
  modalCategory.innerText = project.category_name;
  
  // Вставляем описание
  const modalBodyText = document.querySelector('.modal-body .body-text');
  modalBodyText.innerText = project.description || 'Описание готовится...';
  
  // Вставляем все фотографии из галереи проекта
  const gallery = document.querySelector('.modal-gallery');
  gallery.innerHTML = ''; // Очищаем от старых
  
  if (project.gallery && project.gallery.length > 0) {
    project.gallery.forEach(imgUrl => {
      const img = document.createElement('img');
      img.src = imgUrl;
      img.style.width = '100%';
      gallery.appendChild(img);
    });
  } else {
    gallery.innerHTML = '<div class="modal-img-placeholder">Нет фотографий</div>';
  }
  
  modal.classList.add('active');
  document.body.classList.add('no-scroll');
}

function closeModal() {
  modal.classList.remove('active');
  document.body.classList.remove('no-scroll');
}

modalClose.addEventListener('click', closeModal);
// Закрытие по клику на темный фон вокруг окна
modal.addEventListener('click', (e) => {
  if (e.target === modal) closeModal();
});

// Запускаем загрузку портфолио при открытии сайта
loadPortfolio();
loadReviews();
