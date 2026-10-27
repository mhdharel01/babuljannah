// assets/js/main.js

document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. Toggle Mobile Menu ---
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // --- 2. Hero Slider Banner ---
    let currentSlide = 0;
    const slides = document.querySelectorAll('.hero-slide');
    if (slides.length > 0) {
        // Tampilkan slide pertama
        slides[currentSlide].classList.remove('opacity-0');
        slides[currentSlide].classList.add('opacity-100');
        
        setInterval(function() {
            slides[currentSlide].classList.remove('opacity-100');
            slides[currentSlide].classList.add('opacity-0');
            
            currentSlide = (currentSlide + 1) % slides.length;
            
            slides[currentSlide].classList.remove('opacity-0');
            slides[currentSlide].classList.add('opacity-100');
        }, 5000); // ganti setiap 5 detik
    }

    // --- 3. Multi-step Form Handlers ---
    // Multi-step form navigation and validation is handled specifically within
    // formulir.php and edit_data_santri.php to support dynamic steps & custom validation.

    // Validasi langsung pada input angka saja (NIK, NISN, KK, No WA)
    const numericInputs = document.querySelectorAll('.numeric-only');
    numericInputs.forEach(input => {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    });

    // --- 4. Animated Number Counter (Statistik Interaktif) ---
    const counters = document.querySelectorAll('.counter-value');
    if (counters.length > 0) {
        const counterObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counter = entry.target;
                    const target = parseInt(counter.getAttribute('data-target'));
                    const duration = 2000; // 2 detik
                    const step = Math.ceil(target / (duration / 20));
                    let current = 0;

                    const updateCounter = () => {
                        current += step;
                        if (current >= target) {
                            counter.innerText = target;
                        } else {
                            counter.innerText = current;
                            setTimeout(updateCounter, 20);
                        }
                    };

                    updateCounter();
                    observer.unobserve(counter);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(c => counterObserver.observe(c));
    }

    // --- 5. Interactive Cost Breakdown Tabs (Tab Biaya Pendidikan) ---
    const costTabs = document.querySelectorAll('.cost-tab-btn');
    const costPanels = document.querySelectorAll('.cost-tab-panel');
    if (costTabs.length > 0) {
        costTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const targetPanel = this.getAttribute('data-tab');

                // Update styling tombol tab
                costTabs.forEach(t => {
                    t.classList.remove('bg-primary', 'text-white', 'shadow-lg');
                    t.classList.add('bg-gray-100', 'text-gray-700', 'hover:bg-gray-200');
                });
                this.classList.remove('bg-gray-100', 'text-gray-700', 'hover:bg-gray-200');
                this.classList.add('bg-primary', 'text-white', 'shadow-lg');

                // Tampilkan panel yang sesuai
                costPanels.forEach(panel => {
                    if (panel.id === targetPanel) {
                        panel.classList.remove('hidden');
                        panel.classList.add('block', 'animate-fadeIn');
                    } else {
                        panel.classList.remove('block', 'animate-fadeIn');
                        panel.classList.add('hidden');
                    }
                });
            });
        });
    }

    // --- 6. Interactive FAQ Accordion (Tanya Jawab) ---
    const faqButtons = document.querySelectorAll('.faq-accordion-btn');
    if (faqButtons.length > 0) {
        faqButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const content = this.nextElementSibling;
                const icon = this.querySelector('.faq-icon');
                
                // Toggle accordion active state
                const isExpanded = !content.classList.contains('hidden');
                
                if (isExpanded) {
                    content.classList.add('hidden');
                    if (icon) icon.classList.remove('rotate-180', 'text-secondary');
                } else {
                    content.classList.remove('hidden');
                    if (icon) icon.classList.add('rotate-180', 'text-secondary');
                }
            });
        });
    }
});
