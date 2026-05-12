// ========== APPLY SETTINGS FROM ADMIN PANEL ==========
function applySettingsFromData(s) {
    if (!s) return;

    function esc(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    // Hero
    if (s.hero) {
        const badge = document.querySelector('.hero-badge');
        if (badge && s.hero.badge) badge.textContent = s.hero.badge;

        const heroName = document.querySelector('.hero-name');
        if (heroName && s.hero.name) {
            heroName.innerHTML = esc(s.hero.name) + '<span class="cursor">|</span>';
        }

        const subtitle = document.querySelector('.hero-subtitle');
        if (subtitle && s.hero.subtitle) {
            subtitle.innerHTML = esc(s.hero.subtitle).replace(/\n/g, '<br>');
        }

        // Hero stats HTML'den gelir, dinamik değiştirilmez
    }

    // About
    if (s.about) {
        const aboutH3 = document.querySelector('.about-content h3');
        if (aboutH3 && s.about.heading) aboutH3.textContent = s.about.heading;

        if (s.about.paragraphs && s.about.paragraphs.length) {
            const ps = document.querySelectorAll('.about-content > p');
            s.about.paragraphs.forEach((text, i) => {
                if (ps[i]) ps[i].textContent = text;
            });
        }

        if (s.about.features && s.about.features.length) {
            const featContainer = document.querySelector('.about-features');
            if (featContainer) {
                featContainer.innerHTML = s.about.features.map(f => `
                    <div class="about-feature">
                        <i class="fas fa-check-circle"></i>
                        <span>${esc(f)}</span>
                    </div>
                `).join('');
            }
        }
    }

    // Services
    if (s.services && s.services.length) {
        const grid = document.querySelector('.services-grid');
        if (grid) {
            grid.innerHTML = s.services.map(svc => `
                <div class="service-card${svc.featured ? ' featured' : ''}">
                    ${svc.featured ? '<div class="service-badge">Popüler</div>' : ''}
                    ${svc.image ? `<div class="service-img"><img src="${esc(svc.image)}" alt="${esc(svc.name)}" loading="lazy"></div>` : `<div class="service-icon"><i class="fas ${esc(svc.icon || 'fa-comments')}"></i></div>`}
                    <h3>${esc(svc.name)}</h3>
                    <p>${esc(svc.description)}</p>
                    <div class="service-price">${esc(svc.priceLabel || (svc.price ? '₺' + Number(svc.price).toLocaleString('tr-TR') : 'İletişime Geçin'))} ${svc.price ? '<span>/ seans</span>' : ''}</div>
                    <a href="#${svc.linkType === 'contact' ? 'contact' : 'appointment'}" class="btn btn-sm">
                        ${svc.linkType === 'contact' ? 'Bilgi Al' : 'Randevu Al'}
                    </a>
                </div>
            `).join('');
        }

        // Update servicePrices object for payment
        window._dynamicServicePrices = {};
        s.services.forEach(svc => {
            if (svc.id && svc.price !== undefined) {
                window._dynamicServicePrices[svc.id] = { name: svc.name, price: Number(svc.price) };
            }
        });
    }

    // Testimonials - veritabanından çek
    loadReviews();

    // Fallback: settings'den testimonials varsa ve API boş dönerse kullan
    if (s.testimonials && s.testimonials.length) {
        window._settingsTestimonials = s.testimonials;
    }

    // Numbers HTML'den gelir, dinamik değiştirilmez

    // Contact
    const contact = s.contact || {};
    const contactCards = document.querySelectorAll('.contact-card p');
    if (contactCards.length >= 4) {
        if (contact.phone) contactCards[0].textContent = contact.phone;
        if (contact.email) contactCards[1].textContent = contact.email;
        if (contact.address) contactCards[2].textContent = contact.address;
        if (contact.workingHours) contactCards[3].textContent = contact.workingHours;
    }

    // Appointment sidebar contact info
    const apptContact = document.querySelector('.appointment-contact');
    if (apptContact) {
        const divs = apptContact.querySelectorAll('div');
        if (divs[0] && contact.phone) divs[0].innerHTML = '<i class="fas fa-phone"></i> ' + esc(contact.phone);
        if (divs[1] && contact.email) divs[1].innerHTML = '<i class="fas fa-envelope"></i> ' + esc(contact.email);
        if (divs[2] && contact.address) divs[2].innerHTML = '<i class="fas fa-map-marker-alt"></i> ' + esc(contact.address);
    }

    // Footer social links
    if (s.social) {
        const socialLinks = document.querySelectorAll('.footer-social a');
        socialLinks.forEach(link => {
            const icon = link.querySelector('i');
            if (!icon) return;
            if (icon.classList.contains('fa-instagram') && s.social.instagram) link.href = s.social.instagram;
            if (icon.classList.contains('fa-x-twitter') && s.social.twitter) link.href = s.social.twitter;
            if (icon.classList.contains('fa-linkedin-in') && s.social.linkedin) link.href = s.social.linkedin;
            if (icon.classList.contains('fa-youtube') && s.social.youtube) link.href = s.social.youtube;
        });
    }

    // WhatsApp button
    if (s.social && s.social.whatsapp) {
        const waBtn = document.querySelector('.whatsapp-float');
        if (waBtn) waBtn.href = 'https://wa.me/' + s.social.whatsapp;
    }

    // Appointment form - dynamic service options & prices
    if (s.services && s.services.length) {
        const serviceSelect = document.getElementById('serviceType');
        if (serviceSelect) {
            serviceSelect.innerHTML = '<option value="">Seçiniz...</option>' +
                s.services.map(svc => {
                    const priceText = svc.price > 0 ? ' - ₺' + Number(svc.price).toLocaleString('tr-TR') : (svc.priceLabel ? ' - ' + svc.priceLabel : '');
                    return `<option value="${esc(svc.id)}">${esc(svc.name)}${priceText}</option>`;
                }).join('');
        }
    }

    // Gallery
    if (s.gallery && s.gallery.length) {
        const gGrid = document.querySelector('.gallery-grid');
        if (gGrid) {
            gGrid.innerHTML = s.gallery.map(item => {
                if (item.type === 'video') {
                    return `
                        <div class="gallery-item" onclick="openLightbox('video', '${esc(item.url)}')">
                            <video src="${esc(item.url)}" muted preload="metadata"></video>
                            <div class="play-icon"><i class="fas fa-play"></i></div>
                            <div class="gallery-item-overlay">
                                <h4>${esc(item.title || '')}</h4>
                                <span>${esc(item.date || '')}</span>
                            </div>
                        </div>`;
                }
                return `
                    <div class="gallery-item" onclick="openLightbox('image', '${esc(item.url)}')">
                        <img src="${esc(item.url)}" alt="${esc(item.title || '')}" loading="lazy">
                        <div class="gallery-item-overlay">
                            <h4>${esc(item.title || '')}</h4>
                            <span>${esc(item.date || '')}</span>
                        </div>
                    </div>`;
            }).join('');
        }
    }

    // References
    if (s.references && s.references.length) {
        const track = document.getElementById('referencesTrack');
        const emptyEl = document.getElementById('referencesEmpty');
        const sliderEl = document.querySelector('.references-slider');
        if (track && sliderEl) {
            const items = s.references.map(ref =>
                `<div class="reference-item" title="${esc(ref.name || '')}">
                    <img src="${esc(ref.logo)}" alt="${esc(ref.name || '')}">
                </div>`
            ).join('');
            track.innerHTML = items + items;
            sliderEl.style.display = 'block';
            if (emptyEl) emptyEl.style.display = 'none';
        }
    } else {
        const sliderEl = document.querySelector('.references-slider');
        const emptyEl = document.getElementById('referencesEmpty');
        if (sliderEl) sliderEl.style.display = 'none';
        if (emptyEl) emptyEl.style.display = 'block';
    }

    // About image
    if (s.about && s.about.image) {
        const placeholder = document.querySelector('.about-img-placeholder');
        if (placeholder) {
            placeholder.outerHTML = `<img src="${esc(s.about.image)}" alt="Tuncer" style="width:100%;aspect-ratio:3/4;object-fit:cover;border-radius:16px;">`;
        }
    }

    // Appointment section
    if (s.appointment) {
        const apptTitle = document.querySelector('#appointment .section-title');
        if (apptTitle && s.appointment.title) apptTitle.textContent = s.appointment.title;

        const apptDesc = document.querySelector('#appointment .section-desc');
        if (apptDesc && s.appointment.description) apptDesc.textContent = s.appointment.description;

        const apptSideTitle = document.querySelector('.appointment-info h3');
        if (apptSideTitle && s.appointment.sideTitle) apptSideTitle.textContent = s.appointment.sideTitle;

        if (s.appointment.benefits && s.appointment.benefits.length) {
            const benefitsList = document.querySelector('.appointment-info ul');
            if (benefitsList) {
                benefitsList.innerHTML = s.appointment.benefits.map(b =>
                    `<li><i class="fas fa-check"></i> ${esc(b)}</li>`
                ).join('');
            }
        }

        // Update time slots
        if (s.appointment.timeSlots && s.appointment.timeSlots.length) {
            window._customTimeSlots = s.appointment.timeSlots;
        }
    }

    // SEO
    if (s.seo) {
        if (s.seo.title) document.title = s.seo.title;
        const metaDesc = document.querySelector('meta[name="description"]');
        if (metaDesc && s.seo.description) metaDesc.setAttribute('content', s.seo.description);
        const metaKeys = document.querySelector('meta[name="keywords"]');
        if (metaKeys && s.seo.keywords) metaKeys.setAttribute('content', s.seo.keywords);
    }

    // Footer
    if (s.footer) {
        const footerP = document.querySelector('.footer-brand p');
        if (footerP && s.footer.description) footerP.textContent = s.footer.description;

        const nlP = document.querySelector('.footer-newsletter p');
        if (nlP && s.footer.newsletter) nlP.textContent = s.footer.newsletter;

        const copyP = document.querySelector('.footer-bottom p');
        if (copyP && s.footer.copyright) {
            copyP.innerHTML = '&copy; ' + esc(s.footer.copyright) + ' | <a href="admin.html" style="color:rgba(255,255,255,0.3);text-decoration:none;">Admin</a>';
        }
    }

    // Logo
    if (s.logo) {
        document.querySelectorAll('.logo-img').forEach(img => {
            img.src = s.logo;
        });
        const favicon = document.querySelector('link[rel="icon"]');
        if (favicon) favicon.href = s.logo;
    }

}

// Sunucudan ayarları çek ve uygula
(function() {
    fetch('api/settings.php')
        .then(res => res.ok ? res.json() : null)
        .then(data => {
            if (data && typeof data === 'object') {
                applySettingsFromData(data);
            }
        })
        .catch(() => {});
})();

// ========== LIGHTBOX ==========
window.openLightbox = function(type, url) {
    const lb = document.getElementById('lightbox');
    const content = document.getElementById('lightboxContent');
    if (type === 'video') {
        content.innerHTML = `<video src="${url}" controls autoplay style="max-width:90vw;max-height:85vh;"></video>`;
    } else {
        content.innerHTML = `<img src="${url}" style="max-width:90vw;max-height:85vh;">`;
    }
    lb.classList.add('active');
};

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

// Sayfa yüklenince hero sayıları başlat
animateCounters();

// ========== NUMBERS SECTION COUNTER ==========
function animateNumbers() {
    const numbers = document.querySelectorAll('.number-value');
    numbers.forEach(num => {
        const target = parseInt(num.getAttribute('data-target'));
        if (!target) return;
        const duration = 2500;
        const start = performance.now();

        function update(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            num.textContent = Math.floor(target * eased).toLocaleString('tr-TR');
            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                num.textContent = target.toLocaleString('tr-TR');
            }
        }
        requestAnimationFrame(update);
    });
}

// Numbers bölümü görünür olunca animasyonu başlat
const numbersSection = document.querySelector('.numbers');
if (numbersSection) {
    const numbersObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateNumbers();
                numbersObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    numbersObserver.observe(numbersSection);
}

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

// Re-observe dynamically rebuilt elements (from applySettings)
function reobserveFadeElements() {
    document.querySelectorAll(
        '.service-card, .testimonial-card, .number-card'
    ).forEach(el => {
        if (!el.classList.contains('fade-in')) {
            el.classList.add('fade-in');
            fadeObserver.observe(el);
        }
    });
}
reobserveFadeElements();

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
function getLocalBlogPosts() {
    return [];
}

async function loadBlogPosts() {
    const blogGrid = document.querySelector('.blog-grid');
    if (!blogGrid) return;

    let posts = null;

    try {
        const res = await fetch('api/blog.php');
        if (res.ok) {
            const data = await res.json();
            if (data.length > 0) posts = data;
        }
    } catch (err) { /* fallback */ }

    if (!posts) posts = getLocalBlogPosts();
    if (posts.length === 0) return;

    window._blogPosts = posts;

    blogGrid.innerHTML = posts.slice(0, 3).map((post, i) => `
        <article class="blog-card" data-blog-index="${i}" style="cursor:pointer;">
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
                <a href="#" class="blog-read-more" onclick="event.preventDefault();">Devamını Oku <i class="fas fa-arrow-right"></i></a>
            </div>
        </article>
    `).join('');

    blogGrid.querySelectorAll('.blog-card').forEach(el => {
        el.classList.add('fade-in');
        fadeObserver.observe(el);
        el.addEventListener('click', () => {
            const idx = el.dataset.blogIndex;
            openBlogModal(window._blogPosts[idx]);
        });
    });
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('tr-TR', {
        day: 'numeric', month: 'long', year: 'numeric'
    });
}

loadBlogPosts();

// ========== BLOG MODAL ==========
function renderMarkdownSimple(text) {
    if (!text) return '';
    return text
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/^### (.+)$/gm, '<h3>$1</h3>')
        .replace(/^## (.+)$/gm, '<h2>$1</h2>')
        .replace(/^# (.+)$/gm, '<h1>$1</h1>')
        .replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>')
        .replace(/\n\n/g, '</p><p>')
        .replace(/\n/g, '<br>')
        .replace(/^/, '<p>').replace(/$/, '</p>');
}

function openBlogModal(post) {
    const modal = document.getElementById('blogModal');
    if (!modal) return;
    document.getElementById('blogModalCategory').textContent = post.category || '';
    document.getElementById('blogModalTitle').textContent = post.title || '';
    document.getElementById('blogModalDate').textContent = formatDate(post.date || post.created_at);
    document.getElementById('blogModalReadTime').textContent = post.readTime || post.read_time || '5 dk';
    document.getElementById('blogModalContent').innerHTML = renderMarkdownSimple(post.content || post.summary || '');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeBlogModal() {
    const modal = document.getElementById('blogModal');
    if (modal) modal.classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('click', (e) => {
    const modal = document.getElementById('blogModal');
    if (e.target === modal) closeBlogModal();
});

const blogModalCloseBtn = document.getElementById('blogModalClose');
if (blogModalCloseBtn) blogModalCloseBtn.addEventListener('click', closeBlogModal);

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeBlogModal();
});

// ========== APPOINTMENT AVAILABILITY ==========
const dateInput = document.getElementById('appointmentDate');
const timeSelect = document.getElementById('appointmentTime');
let bookedSlots = []; // { date: '2026-04-10', time: '09:00' }

// Load booked appointments
async function loadBookedSlots() {
    try {
        const res = await fetch('api/appointments.php?slots=1');
        if (res.ok) {
            const data = await res.json();
            bookedSlots = data.map(a => ({ date: a.date, time: a.time }));
            return;
        }
    } catch (err) { /* offline */ }
    // Fallback to localStorage
    const saved = localStorage.getItem('tunceryaziyor_appointments');
    if (saved) {
        bookedSlots = JSON.parse(saved).map(a => ({ date: a.date, time: a.time }));
    }
}
loadBookedSlots();

if (dateInput) {
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    dateInput.min = tomorrow.toISOString().split('T')[0];

    // When date changes, update available time slots
    dateInput.addEventListener('change', function() {
        updateTimeSlots(this.value);
    });
}

function updateTimeSlots(selectedDate) {
    if (!timeSelect) return;
    const allTimes = window._customTimeSlots || ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
    const takenTimes = bookedSlots
        .filter(s => s.date === selectedDate)
        .map(s => s.time);

    timeSelect.innerHTML = '<option value="">Saat Seçiniz...</option>' +
        allTimes.map(t => {
            const isTaken = takenTimes.includes(t);
            return `<option value="${t}" ${isTaken ? 'disabled style="color:#999;text-decoration:line-through;"' : ''}>${t}${isTaken ? ' (Dolu)' : ''}</option>`;
        }).join('');

    // Show availability summary
    const available = allTimes.length - takenTimes.length;
    const existingBadge = document.getElementById('availabilityBadge');
    if (existingBadge) existingBadge.remove();

    if (selectedDate) {
        const badge = document.createElement('div');
        badge.id = 'availabilityBadge';
        badge.style.cssText = 'margin-top:8px;font-size:0.8rem;padding:6px 12px;border-radius:8px;display:inline-block;';
        if (available === 0) {
            badge.style.background = '#fee2e2';
            badge.style.color = '#dc2626';
            badge.innerHTML = '<i class="fas fa-times-circle"></i> Bu gün tamamen dolu. Lütfen başka bir tarih seçin.';
        } else if (takenTimes.length > 0) {
            badge.style.background = '#fef3c7';
            badge.style.color = '#d97706';
            badge.innerHTML = `<i class="fas fa-info-circle"></i> ${available} saat müsait, ${takenTimes.length} saat dolu.`;
        } else {
            badge.style.background = '#dcfce7';
            badge.style.color = '#16a34a';
            badge.innerHTML = '<i class="fas fa-check-circle"></i> Tüm saatler müsait!';
        }
        dateInput.parentNode.appendChild(badge);
    }
}

// ========== SERVICE PRICES ==========
// Always read from dynamically loaded settings to avoid stale hardcoded prices
function getServicePrices() {
    return window._dynamicServicePrices && Object.keys(window._dynamicServicePrices).length
        ? window._dynamicServicePrices
        : {};
}

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

    const currentPrices = getServicePrices();
    const service = currentPrices[appointmentData.serviceType];

    if (!service) {
        showToast('Lütfen bir hizmet türü seçiniz. Sayfa henüz yüklenmemiş olabilir, lütfen sayfayı yenileyip tekrar deneyiniz.');
        return;
    }

    // Check if slot is taken
    const slotTaken = bookedSlots.some(s => s.date === appointmentData.date && s.time === appointmentData.time);
    if (slotTaken) {
        showToast('Bu saat dolu! Lütfen başka bir saat seçiniz.');
        return;
    }

    // Show payment summary
    const dateFormatted = new Date(appointmentData.date).toLocaleDateString('tr-TR', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });

    const priceDisplay = service.price > 0 ? `₺${service.price.toLocaleString('tr-TR')}` : 'Ücretsiz';

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
            <span>${priceDisplay}</span>
        </div>
    `;

    // If price is 0, save appointment directly without payment
    if (service.price === 0) {
        try {
            const res = await fetch('api/appointments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(appointmentData)
            });
            if (res.ok) {
                saveAppointmentLocally(appointmentData, 0);
                bookedSlots.push({ date: appointmentData.date, time: appointmentData.time });
                showToast('Randevunuz başarıyla oluşturuldu! En kısa sürede sizinle iletişime geçeceğiz.', 'success');
                appointmentForm.reset();
                return;
            }
        } catch (err) { /* fallback below */ }
        saveAppointmentLocally(appointmentData, 0);
        bookedSlots.push({ date: appointmentData.date, time: appointmentData.time });
        sendAppointmentNotification(appointmentData, service);
        showToast('Randevunuz kaydedildi! En kısa sürede sizinle iletişime geçeceğiz.', 'success');
        appointmentForm.reset();
        return;
    }

    // iyzico ödeme başlat (only when price > 0)
    let iyzicoError = null;
    try {
        const res = await fetch('api/payment.php?action=initialize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...appointmentData, price: service.price })
        });

        const result = await res.json().catch(() => ({}));

        if (res.ok && result.checkoutFormContent) {
            const paymentFormContainer = document.getElementById('paymentForm');
            paymentFormContainer.innerHTML = '<div id="iyzipay-checkout-form" class="responsive"></div>';

            const mount = document.getElementById('iyzipay-checkout-form');
            mount.innerHTML = result.checkoutFormContent;

            // innerHTML ile eklenen <script>'lar calismaz — yeniden olustur
            mount.querySelectorAll('script').forEach(oldScript => {
                const newScript = document.createElement('script');
                for (const attr of oldScript.attributes) {
                    newScript.setAttribute(attr.name, attr.value);
                }
                newScript.text = oldScript.textContent;
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });

            paymentSection.style.display = 'block';
            paymentSection.scrollIntoView({ behavior: 'smooth' });
            return;
        }

        iyzicoError = result.errorMessage || result.errorCode || 'Bilinmeyen hata';
        console.error('[iyzico]', result);
    } catch (err) {
        iyzicoError = 'Sunucuya ulaşılamadı';
        console.error('[iyzico] fetch failed:', err);
    }

    // iyzico bağlantısı kurulamadı — randevu backend'de pending, kullanıcıya uyarı
    bookedSlots.push({ date: appointmentData.date, time: appointmentData.time });

    showPaymentUnavailable(service.price, iyzicoError);
    paymentSection.style.display = 'block';
    paymentSection.scrollIntoView({ behavior: 'smooth' });
});

function saveAppointmentLocally(data, price) {
    const appointments = JSON.parse(localStorage.getItem('tunceryaziyor_appointments') || '[]');
    appointments.push({
        id: Date.now().toString(),
        fullName: data.fullName,
        email: data.email,
        phone: data.phone,
        serviceType: data.serviceType,
        date: data.date,
        time: data.time,
        message: data.message,
        price: price,
        status: 'pending',
        createdAt: new Date().toISOString()
    });
    localStorage.setItem('tunceryaziyor_appointments', JSON.stringify(appointments));
}

async function sendAppointmentNotification(data, service) {
    // Try to send via API (email notification)
    try {
        await fetch('api/notify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'new_appointment',
                fullName: data.fullName,
                email: data.email,
                phone: data.phone,
                service: service.name,
                date: data.date,
                time: data.time,
                price: service.price
            })
        });
    } catch (err) { /* offline - notification skipped */ }
}

function showPaymentUnavailable(price, errorDetail) {
    const paymentFormEl = document.getElementById('paymentForm');
    const detailLine = errorDetail
        ? `<div style="margin-top:10px;font-size:0.8rem;color:#9a3412;opacity:0.8;">Hata: ${escapeHtml(String(errorDetail))}</div>`
        : '';
    paymentFormEl.innerHTML = `
        <div class="payment-info-box" style="background:#fff7ed;border-radius:12px;padding:24px;margin-bottom:24px;font-size:0.95rem;color:#9a3412;display:flex;align-items:flex-start;gap:14px;">
            <i class="fas fa-exclamation-triangle" style="font-size:1.6rem;color:#f59e0b;margin-top:2px;"></i>
            <div>
                <strong style="display:block;margin-bottom:6px;color:#7c2d12;">Ödeme şu anda tamamlanamadı</strong>
                <span>Randevu talebiniz kaydedildi ancak ödeme alınamadı. ₺${price.toLocaleString('tr-TR')} tutarındaki ödemeyi tamamlamak için WhatsApp veya e-posta ile bize ulaşın.</span>
                ${detailLine}
            </div>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center;">
            <a href="https://wa.me/905326034993" target="_blank" rel="noopener" class="btn btn-primary" style="flex:1;min-width:200px;">
                <i class="fab fa-whatsapp"></i> WhatsApp ile İletişim
            </a>
            <a href="mailto:info@tkdanismanlik.com" class="btn btn-primary" style="flex:1;min-width:200px;background:linear-gradient(135deg,#1a1a2e,#16213e);">
                <i class="fas fa-envelope"></i> E-posta Gönder
            </a>
        </div>
    `;
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

// ========== LOAD REVIEWS FROM DB ==========
async function loadReviews() {
    const tGrid = document.querySelector('.testimonials-grid');
    if (!tGrid) return;

    function esc(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    let reviews = null;
    try {
        const res = await fetch('api/contact.php?action=reviews');
        if (res.ok) reviews = await res.json();
    } catch (err) { /* offline */ }

    // Fallback: settings testimonials
    if (!reviews || reviews.length === 0) {
        reviews = window._settingsTestimonials || [];
    }

    if (reviews.length === 0) return;

    tGrid.innerHTML = reviews.map(t => `
        <div class="testimonial-card">
            <div class="testimonial-stars">
                ${'<i class="fas fa-star"></i>'.repeat(t.stars || 5)}
            </div>
            <p>"${esc(t.text)}"</p>
            <div class="testimonial-author">
                <div class="testimonial-avatar"><i class="fas fa-user"></i></div>
                <div>
                    <strong>${esc(t.name)}</strong>
                    <span>${esc(t.role)}</span>
                </div>
            </div>
        </div>
    `).join('');
}

// ========== REVIEW FORM ==========
const toggleReviewBtn = document.getElementById('toggleReviewForm');
const reviewForm = document.getElementById('reviewForm');

if (toggleReviewBtn && reviewForm) {
    toggleReviewBtn.addEventListener('click', () => {
        reviewForm.style.display = reviewForm.style.display === 'none' ? 'block' : 'none';
        toggleReviewBtn.innerHTML = reviewForm.style.display === 'none'
            ? '<i class="fas fa-pen"></i> Siz de Yorum Yazın'
            : '<i class="fas fa-times"></i> İptal';
    });
}

// Star rating
const starRating = document.getElementById('starRating');
const starInput = document.getElementById('reviewStars');
if (starRating) {
    const stars = starRating.querySelectorAll('i');
    function setStars(rating) {
        stars.forEach((s, i) => {
            s.classList.toggle('active', i < rating);
        });
        if (starInput) starInput.value = rating;
    }
    stars.forEach(star => {
        star.addEventListener('click', () => setStars(parseInt(star.dataset.star)));
        star.addEventListener('mouseenter', () => {
            const val = parseInt(star.dataset.star);
            stars.forEach((s, i) => s.style.color = i < val ? 'var(--accent)' : '#ddd');
        });
    });
    starRating.addEventListener('mouseleave', () => {
        const current = parseInt(starInput.value);
        stars.forEach((s, i) => s.style.color = '');
    });
    setStars(5);
}

// Review submit
if (reviewForm) {
    reviewForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        const review = {
            id: 'r' + Date.now(),
            name: fd.get('reviewName'),
            role: fd.get('reviewRole') || 'Danışan',
            text: fd.get('reviewText'),
            stars: parseInt(fd.get('reviewStars')) || 5,
            approved: false,
            createdAt: new Date().toISOString()
        };

        // Save to localStorage (pending reviews)
        const pending = JSON.parse(localStorage.getItem('tunceryaziyor_pending_reviews') || '[]');
        pending.push(review);
        localStorage.setItem('tunceryaziyor_pending_reviews', JSON.stringify(pending));

        // Try to send to API
        try {
            await fetch('api/contact.php?action=review', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(review)
            });
        } catch (err) { /* offline */ }

        showToast('Yorumunuz gönderildi! İncelendikten sonra yayınlanacaktır.');
        this.reset();
        reviewForm.style.display = 'none';
        toggleReviewBtn.innerHTML = '<i class="fas fa-pen"></i> Siz de Yorum Yazın';
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
