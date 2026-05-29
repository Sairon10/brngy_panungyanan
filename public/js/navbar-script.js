// Enhanced Navbar Interactivity
document.addEventListener('DOMContentLoaded', function() {
    // 1. Smooth scroll for anchor links
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link[href*="#"]');
    const isLandingPage = document.querySelector('.hero-section') !== null;

    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Instantly highlight clicked link
            document.querySelectorAll('.navbar-nav .nav-link').forEach(l => l.classList.remove('active'));
            this.classList.add('active');

            const href = this.getAttribute('href');
            if (href.includes('#') && isLandingPage) {
                const parts = href.split('#');
                const targetId = parts[1];
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    
                    // Close mobile menu if open
                    const offcanvasMenu = document.getElementById('navbarNav');
                    if (offcanvasMenu && typeof bootstrap !== 'undefined') {
                        const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasMenu);
                        if (bsOffcanvas) bsOffcanvas.hide();
                    }
                }
            }
        });
    });

    // 2. Dynamic ScrollSpy
    const spySections = document.querySelectorAll('section[id], section.hero-section');
    const allNavLinks = document.querySelectorAll('.navbar-nav .nav-link');
    
    if (isLandingPage) {
        function updateScrollSpy() {
            let currentId = 'hero-section';

            spySections.forEach(section => {
                if (!section) return;
                const rect = section.getBoundingClientRect();
                // Check if top of section is within the top third of the viewport
                if (rect.top <= (window.innerHeight / 3)) {
                    currentId = section.getAttribute('id') || 'hero-section';
                }
            });

            allNavLinks.forEach(link => {
                link.classList.remove('active');
                const href = link.getAttribute('href');
                if (!href) return;
                
                if (currentId === 'hero-section' && (href === 'index.php' || href === '')) {
                    link.classList.add('active');
                } else if (currentId !== 'hero-section' && href.includes('#' + currentId)) {
                    link.classList.add('active');
                }
            });
        }

        window.addEventListener('scroll', updateScrollSpy);
        setTimeout(updateScrollSpy, 100); // Run once on load
    } else {
        // Static highlighting for other pages based on URL
        const currentPath = window.location.pathname.split('/').pop().split('?')[0] || 'index.php';
        allNavLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href && (href === currentPath || (currentPath === 'index.php' && href === '#'))) {
                link.classList.add('active');
            }
        });
    }

    // 3. Close offcanvas on normal navigation
    const offcanvasMenu = document.getElementById('navbarNav');
    if (offcanvasMenu && typeof bootstrap !== 'undefined') {
        const mobileNavLinks = offcanvasMenu.querySelectorAll('.nav-link:not([href*="#"])');
        mobileNavLinks.forEach(link => {
            link.addEventListener('click', function() {
                const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasMenu);
                if (bsOffcanvas) {
                    setTimeout(() => bsOffcanvas.hide(), 100);
                }
            });
        });
    }

    // 4. Navbar scroll effect
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 10) {
                navbar.classList.add('navbar-scrolled');
            } else {
                navbar.classList.remove('navbar-scrolled');
            }
        });
    }

    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
