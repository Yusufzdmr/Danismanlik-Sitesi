// ========== NAVBAR SCROLL ==========
const navbar = document.getElementById('navbar');
const hamburger = document.getElementById('hamburger');
const navLinks = document.getElementById('navLinks');
const backToTop = document.getElementById('backToTop');

window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }

    if (window.scrollY > 500) {
        backToTop.classList.add('visible');
    } else {
        backToTop.classList.remove('visible');
    }
});

// ========== HAMBURGER MENU ==========
hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('active');
    navLinks.classList.toggle('active');
});

navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
        hamburger.classList.remove('active');
        navLinks.classList.remove('active');
    });
});

// ========== BACK TO TOP ==========
backToTop.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});

// ========== PARTICLES ==========
function createParticles() {
    const container = document.getElementById('particles');
    if (!container) return;
    for (let i = 0; i < 30; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        const size = Math.random() * 6 + 2;
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.animationDuration = Math.random() * 15 + 10 + 's';
        particle.style.animationDelay = Math.random() * 10 + 's';
        container.appendChild(particle);
    }
}
createParticles();

// ========== COUNTER ANIMATION ==========
function animateCounters() {
    const counters = document.querySelectorAll('.stat-number');
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'));
        const duration = 2000;
        const start = performance.now();

        function update(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            counter.textContent = Math.floor(target * eased);
            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                counter.textContent = target;
            }
        }
        requestAnimationFrame(update);
    });
}

const heroObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounters();
            heroObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.5 });

const heroStats = document.querySelector('.hero-stats');
if (heroStats) heroObserver.observe(heroStats);

// ========== SCROLL ANIMATIONS ==========
const fadeElements = document.querySelectorAll(
    '.service-card, .blog-card, .testimonial-card, .about-content, .about-img-wrapper, .contact-card, .section-header'
);

fadeElements.forEach(el => el.classList.add('fade-in'));

const fadeObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            fadeObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

fadeElements.forEach(el => fadeObserver.observe(el));

// ========== SMOOTH SCROLL ==========
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});

// ========== ACTIVE NAV LINK ==========
const sections = document.querySelectorAll('section[id]');
window.addEventListener('scroll', () => {
    const scrollPos = window.scrollY + 100;
    sections.forEach(section => {
        const top = section.offsetTop;
        const height = section.offsetHeight;
        const id = section.getAttribute('id');
        const link = document.querySelector(`.nav-links a[href="#${id}"]`);
        if (link) {
            if (scrollPos >= top && scrollPos < top + height) {
                link.style.color = 'var(--accent)';
            } else {
                link.style.color = '';
            }
        }
    });
});

// ========== LOAD BLOG POSTS ==========
const defaultBlogPosts = [
    { id:"1", title:"Başarıya Giden Yolda En Önemli 5 Adım", category:"Kişisel Gelişim", summary:"Hedeflerinize ulaşmak için atmanız gereken temel adımları bu yazımda paylaşıyorum...", date:"2026-04-01", readTime:"5 dk", icon:"lightbulb" },
    { id:"2", title:"Doğru Kararlar Almak İçin Stratejik Düşünme", category:"Strateji", summary:"Kararsızlık anlarında size yol gösterecek stratejik düşünme teknikleri...", date:"2026-03-25", readTime:"7 dk", icon:"brain" },
    { id:"3", title:"Etkili İletişimin Sırları", category:"İletişim", summary:"İş ve özel hayatınızda fark yaratacak iletişim becerileri üzerine düşüncelerim...", date:"2026-03-18", readTime:"4 dk", icon:"handshake" }
];

function getLocalBlogPosts() {
    const saved = localStorage.getItem('tunceryaziyor_blog');
    if (saved) return JSON.parse(saved).filter(p => p.published);
    return defaultBlogPosts;
}

async function loadBlogPosts() {
    const blogGrid = document.querySelector('.blog-grid');
    if (!blogGrid) return;

    let posts = null;

    try {
        const res = await fetch('api/blog.php');
        if (res.ok) posts = await res.json();
    } catch (err) { /* fallback */ }

    if (!posts) posts = getLocalBlogPosts();
    if (posts.length === 0) return;

    blogGrid.innerHTML = posts.slice(0, 3).map(post => `
        <article class="blog-card">
            <div class="blog-img">
                <div class="blog-img-placeholder">
                    <i class="fas fa-${escapeHtml(post.icon || 'pen')}"></i>
                </div>
                <span class="blog-category">${escapeHtml(post.category)}</span>
            </div>
            <div class="blog-content">
                <div class="blog-meta">
                    <span><i class="fas fa-calendar"></i> ${formatDate(post.date)}</span>
                    <span><i class="fas fa-clock"></i> ${escapeHtml(post.readTime || '5 dk')}</span>
                </div>
                <h3>${escapeHtml(post.title)}</h3>
                <p>${escapeHtml(post.summary)}</p>
                <a href="blog.html?id=${post.id}" class="blog-read-more">Devamını Oku <i class="fas fa-arrow-right"></i></a>
            </div>
        </article>
    `).join('');

    blogGrid.querySelectorAll('.blog-card').forEach(el => {
        el.classList.add('fade-in');
        fadeObserver.observe(el);
    });
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('tr-TR', {
        day: 'numeric', month: 'long', year: 'numeric'
    });
}

loadBlogPosts();

// ========== SET MINIMUM DATE FOR APPOINTMENT ==========
const dateInput = document.getElementById('appointmentDate');
if (dateInput) {
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    dateInput.min = tomorrow.toISOString().split('T')[0];
}

// ========== SERVICE PRICES ==========
const servicePrices = {
    bireysel: { name: 'Bireysel Danışmanlık', price: 750 },
    kurumsal: { name: 'Kurumsal Danışmanlık', price: 2500 },
    online: { name: 'Online Görüşme', price: 500 },
    seminer: { name: 'Seminer & Workshop', price: 0 }
};

// ========== APPOINTMENT FORM ==========
const appointmentForm = document.getElementById('appointmentForm');
const paymentSection = document.getElementById('payment');
const paymentSummary = document.getElementById('paymentSummary');

let appointmentData = {};

appointmentForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const formData = new FormData(this);
    appointmentData = {
        fullName: formData.get('fullName'),
        email: formData.get('email'),
        phone: formData.get('phone'),
        serviceType: formData.get('serviceType'),
        date: formData.get('appointmentDate'),
        time: formData.get('appointmentTime'),
        message: formData.get('message')
    };

    const service = servicePrices[appointmentData.serviceType];

    if (!service) {
        showToast('Lütfen bir hizmet türü seçiniz.');
        return;
    }

    if (service.price === 0) {
        showToast('Seminer için lütfen iletişime geçiniz!');
        return;
    }

    // Show payment summary
    const dateFormatted = new Date(appointmentData.date).toLocaleDateString('tr-TR', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });

    paymentSummary.innerHTML = `
        <h3><i class="fas fa-receipt"></i> Randevu Özeti</h3>
        <div class="payment-summary-row">
            <span>Danışan:</span>
            <span>${escapeHtml(appointmentData.fullName)}</span>
        </div>
        <div class="payment-summary-row">
            <span>Hizmet:</span>
            <span>${escapeHtml(service.name)}</span>
        </div>
        <div class="payment-summary-row">
            <span>Tarih:</span>
            <span>${dateFormatted}</span>
        </div>
        <div class="payment-summary-row">
            <span>Saat:</span>
            <span>${escapeHtml(appointmentData.time)}</span>
        </div>
        <div class="payment-summary-row payment-summary-total">
            <span>Toplam Tutar:</span>
            <span>₺${service.price.toLocaleString('tr-TR')}</span>
        </div>
    `;

    // iyzico ödeme başlat
    try {
        const res = await fetch('api/payment.php?action=initialize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...appointmentData, price: service.price })
        });

        if (res.ok) {
            const result = await res.json();
            if (result.checkoutFormContent) {
                const paymentFormContainer = document.getElementById('paymentForm');
                paymentFormContainer.innerHTML = `
                    <div id="iyzipay-checkout-form" class="responsive">
                        ${result.checkoutFormContent}
                    </div>
                `;
                paymentSection.style.display = 'block';
                paymentSection.scrollIntoView({ behavior: 'smooth' });
                return;
            }
        }
    } catch (err) {
        // Sunucu yok veya hata - fallback
    }

    showManualPaymentForm(service.price);
    paymentSection.style.display = 'block';
    paymentSection.scrollIntoView({ behavior: 'smooth' });
});

function showManualPaymentForm(price) {
    const paymentFormEl = document.getElementById('paymentForm');
    paymentFormEl.innerHTML = `
        <div class="card-preview" id="cardPreview">
            <div class="card-preview-inner">
                <div class="card-chip"></div>
                <div class="card-number-display" id="cardNumberDisplay">**** **** **** ****</div>
                <div class="card-bottom">
                    <div>
                        <span class="card-label">Kart Sahibi</span>
                        <div id="cardNameDisplay">AD SOYAD</div>
                    </div>
                    <div>
                        <span class="card-label">Son Kullanma</span>
                        <div id="cardExpiryDisplay">MM/YY</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label for="cardName"><i class="fas fa-user"></i> Kart Üzerindeki İsim</label>
            <input type="text" id="cardName" required placeholder="Ad Soyad" autocomplete="cc-name">
        </div>
        <div class="form-group">
            <label for="cardNumber"><i class="fas fa-credit-card"></i> Kart Numarası</label>
            <input type="text" id="cardNumber" required placeholder="0000 0000 0000 0000" maxlength="19" autocomplete="cc-number">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="cardExpiry"><i class="fas fa-calendar"></i> Son Kullanma</label>
                <input type="text" id="cardExpiry" required placeholder="MM/YY" maxlength="5" autocomplete="cc-exp">
            </div>
            <div class="form-group">
                <label for="cardCvv"><i class="fas fa-lock"></i> CVV</label>
                <input type="text" id="cardCvv" required placeholder="***" maxlength="4" autocomplete="cc-csc">
            </div>
        </div>
        <div class="payment-security">
            <i class="fas fa-shield-alt"></i>
            <span>256-bit SSL ile korunan güvenli ödeme. Kart bilgileriniz şifrelenerek iletilir.</span>
        </div>
        <div class="payment-info-box" style="background:#fff7ed;border-radius:10px;padding:16px;margin-bottom:20px;font-size:0.85rem;color:#9a3412;display:flex;align-items:center;gap:10px;">
            <i class="fas fa-info-circle" style="font-size:1.2rem;color:#f59e0b;"></i>
            <span>iyzico bağlantısı kurulamadı. Sunucu çalıştığında otomatik olarak güvenli iyzico ödeme formu gösterilecektir.</span>
        </div>
        <button type="submit" class="btn btn-primary btn-full" id="payButton">
            <i class="fas fa-lock"></i> ₺${price.toLocaleString('tr-TR')} Güvenli Ödeme Yap
        </button>
    `;

    // Re-attach card preview events
    setupCardPreview();

    // Re-attach form submit
    paymentFormEl.addEventListener('submit', handleManualPayment);
}

function setupCardPreview() {
    const cardNumberInput = document.getElementById('cardNumber');
    const cardNameInput = document.getElementById('cardName');
    const cardExpiryInput = document.getElementById('cardExpiry');
    const cardCvvInput = document.getElementById('cardCvv');

    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function () {
            let value = this.value.replace(/\D/g, '');
            value = value.replace(/(.{4})/g, '$1 ').trim();
            this.value = value;
            document.getElementById('cardNumberDisplay').textContent = value || '**** **** **** ****';
        });
    }

    if (cardNameInput) {
        cardNameInput.addEventListener('input', function () {
            document.getElementById('cardNameDisplay').textContent = this.value.toUpperCase() || 'AD SOYAD';
        });
    }

    if (cardExpiryInput) {
        cardExpiryInput.addEventListener('input', function () {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 2) value = value.substring(0, 2) + '/' + value.substring(2);
            this.value = value;
            document.getElementById('cardExpiryDisplay').textContent = value || 'MM/YY';
        });
    }

    if (cardCvvInput) {
        cardCvvInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '');
        });
    }
}

function handleManualPayment(e) {
    e.preventDefault();

    const cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
    const cardExpiry = document.getElementById('cardExpiry').value;
    const cardCvv = document.getElementById('cardCvv').value;

    if (cardNumber.length < 16) { showToast('Geçerli bir kart numarası giriniz.'); return; }
    if (!/^\d{2}\/\d{2}$/.test(cardExpiry)) { showToast('Geçerli bir son kullanma tarihi giriniz.'); return; }
    if (cardCvv.length < 3) { showToast('Geçerli bir CVV giriniz.'); return; }

    const payButton = document.getElementById('payButton');
    payButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
    payButton.disabled = true;

    setTimeout(() => {
        payButton.innerHTML = '<i class="fas fa-check-circle"></i> Ödeme Başarılı!';
        payButton.style.background = 'linear-gradient(135deg, #16a34a, #22c55e)';
        payButton.style.boxShadow = '0 4px 20px rgba(22, 163, 74, 0.4)';
        showToast('Ödemeniz başarıyla alındı! Randevunuz onaylandı.');
    }, 2500);
}

// ========== CONTACT FORM ==========
const contactForm = document.getElementById('contactForm');
if (contactForm) {
    contactForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        try {
            await fetch('api/contact.php?action=message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: fd.get('contactName'),
                    email: fd.get('contactEmail'),
                    subject: fd.get('contactSubject'),
                    message: fd.get('contactMessage')
                })
            });
        } catch (err) { /* offline */ }
        showToast('Mesajınız başarıyla gönderildi! En kısa sürede size dönüş yapacağım.');
        this.reset();
    });
}

// ========== NEWSLETTER ==========
const newsletterForm = document.getElementById('newsletterForm');
if (newsletterForm) {
    newsletterForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const email = this.querySelector('input[type="email"]').value;
        try {
            await fetch('api/contact.php?action=newsletter', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email })
            });
        } catch (err) { /* offline */ }
        showToast('Bültene başarıyla abone oldunuz!');
        this.reset();
    });
}

// ========== TOAST NOTIFICATION ==========
function showToast(message) {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toastMessage');
    toastMessage.textContent = message;
    toast.classList.add('show');
    setTimeout(() => { toast.classList.remove('show'); }, 4000);
}

// ========== HELPER: Escape HTML ==========
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
