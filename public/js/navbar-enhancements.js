// Enhanced Navbar Interactivity
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scroll for anchor links
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link[href*="#"]');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href.includes('#')) {
                const parts = href.split('#');
                const targetId = parts[1];
                const targetPage = parts[0] || 'index.php';
                const currentPage = window.location.pathname.split('/').pop() || 'index.php';
                
                if (targetPage === currentPage || (targetPage === 'index.php' && currentPage === '')) {
                    const targetElement = document.getElementById(targetId);
                    if (targetElement) {
                        e.preventDefault();
                        targetElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                        
                        // Close offcanvas
                        const offcanvasMenu = document.getElementById('navbarNav');
                        if (offcanvasMenu && typeof bootstrap !== 'undefined') {
                            const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasMenu);
                            bsOffcanvas.hide();
                        }
                    }
                }
            }
        });
    });

    // Active link highlighting
    const currentPath = window.location.pathname.split('/').pop() || 'index.php';
    document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href && (href === currentPath || (currentPath === 'index.php' && href === '#'))) {
            link.classList.add('active');
        }
    });

    // Close offcanvas on any nav-link click that isn't an anchor (for normal navigation)
    const offcanvasMenu = document.getElementById('navbarNav');
    if (offcanvasMenu && typeof bootstrap !== 'undefined') {
        const mobileNavLinks = offcanvasMenu.querySelectorAll('.nav-link:not([href*="#"])');
        mobileNavLinks.forEach(link => {
            link.addEventListener('click', function() {
                const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasMenu);
                setTimeout(() => bsOffcanvas.hide(), 100);
            });
        });
    }

    // Navbar scroll effect (removed hide on scroll as per user request to keep it fixed)
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
