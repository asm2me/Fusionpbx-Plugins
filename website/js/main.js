/* ========================================
   VOIP@ Cloud - Main JavaScript
   ======================================== */

// Navbar scroll effect
const navbar = document.getElementById('navbar');
if (navbar) {
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

// Hamburger menu toggle
const hamburger = document.getElementById('hamburger');
const navLinks = document.getElementById('navLinks');
if (hamburger && navLinks) {
    hamburger.addEventListener('click', () => {
        navLinks.classList.toggle('active');
        hamburger.classList.toggle('active');
    });

    // Close menu on link click
    navLinks.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            navLinks.classList.remove('active');
            hamburger.classList.remove('active');
        });
    });
}

// Billing toggle (monthly/yearly)
const billingToggle = document.getElementById('billingToggle');
if (billingToggle) {
    billingToggle.addEventListener('change', function () {
        const isYearly = this.checked;
        const labels = document.querySelectorAll('.toggle-label');
        const amounts = document.querySelectorAll('.amount');

        labels.forEach(label => {
            if (label.dataset.period === 'yearly') {
                label.classList.toggle('active', isYearly);
            } else {
                label.classList.toggle('active', !isYearly);
            }
        });

        amounts.forEach(el => {
            const monthly = el.dataset.monthly;
            const yearly = el.dataset.yearly;
            el.textContent = isYearly ? yearly : monthly;
        });
    });
}

// FAQ toggle
function toggleFaq(button) {
    const item = button.closest('.faq-item');
    const isActive = item.classList.contains('active');

    // Close all
    document.querySelectorAll('.faq-item').forEach(el => {
        el.classList.remove('active');
    });

    // Open clicked (if it wasn't already open)
    if (!isActive) {
        item.classList.add('active');
    }
}

// Provider accordion toggle
function toggleProvider(btn) {
    const item = btn.closest('.provider-item');
    const isActive = item.classList.contains('active');

    // Close all in same accordion
    item.closest('.provider-accordion').querySelectorAll('.provider-item').forEach(el => {
        el.classList.remove('active');
    });

    if (!isActive) {
        item.classList.add('active');
    }
}

// Copy code block
function copyCode(btn) {
    const code = btn.closest('.code-block').querySelector('code');
    navigator.clipboard.writeText(code.textContent).then(() => {
        const icon = btn.querySelector('i');
        icon.className = 'fas fa-check';
        btn.style.color = '#10B981';
        setTimeout(() => {
            icon.className = 'fas fa-copy';
            btn.style.color = '';
        }, 2000);
    });
}

// Contact form handler
function handleContact(e) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;

    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    btn.disabled = true;

    // Simulate send (replace with actual API call)
    setTimeout(() => {
        btn.innerHTML = '<i class="fas fa-check"></i> Message Sent!';
        btn.style.background = '#10B981';
        btn.style.borderColor = '#10B981';
        form.reset();

        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '';
            btn.style.borderColor = '';
            btn.disabled = false;
        }, 3000);
    }, 1500);
}

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});

// Intersection Observer for animations — deferred to avoid forced reflow
if ('IntersectionObserver' in window) {
    requestAnimationFrame(() => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

        const els = document.querySelectorAll('.feature-card, .step, .price-card, .dns-step');
        for (let i = 0; i < els.length; i++) {
            els[i].classList.add('animate-ready');
            observer.observe(els[i]);
        }
    });
}
