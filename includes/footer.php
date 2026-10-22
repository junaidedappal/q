<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

</div> <?php if ($currentPage != 'login.php'): ?>
<footer class="footer-custom mt-auto">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-6 text-center text-lg-start">
                <div class="footer-brand mb-3">
                    <i class="bi bi-mortarboard-fill me-2"></i> QUIDEI EMS
                </div>
                <p class="footer-text mb-4">
                    Providing cutting-edge digital infrastructure for modern education systems.
                </p>
                <div class="footer-badges d-flex justify-content-center justify-content-lg-start">
                    <span class="badge-item"><i class="bi bi-shield-check"></i> Secure</span>
                    <span class="badge-item"><i class="bi bi-lightning-fill"></i> Fast</span>
                </div>
            </div>

            <div class="col-lg-6 text-center text-lg-end">
                <h6 class="footer-heading mb-3">Stay Connected</h6>
                <div class="footer-social mb-3 d-flex justify-content-center justify-content-lg-end">
                    <a href="#"><i class="bi bi-facebook"></i></a>
                    <a href="#"><i class="bi bi-twitter-x"></i></a>
                    <a href="#"><i class="bi bi-linkedin"></i></a>
                    <a href="#"><i class="bi bi-instagram"></i></a>
                </div>
                <p class="footer-contact">
                    <i class="bi bi-envelope-fill me-2"></i>support@quidei.com
                </p>
            </div>
        </div>

        <hr class="footer-divider">

        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="footer-copyright mb-0">
                    © <?php echo date('Y'); ?> <strong><a href="https://infonile.in" target="_blank"
                            class="text-decoration-none">INFONILE</a></strong>
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="footer-version mb-0 mt-2 mt-md-0">
                    <span class="badge bg-light text-dark fw-normal border">Version 1.0</span>
                </p>
            </div>
        </div>
    </div>
</footer>

<style>
.footer-custom {
    background-color: #ffffff;
    border-top: 1px solid #f1f5f9;
    color: var(--slate-900);
}

.footer-brand {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--slate-900);
}

.footer-brand i {
    color: var(--emerald-500);
}

.footer-text {
    color: #64748b;
    max-width: 400px;
    margin: 0 auto;
}

@media (min-width: 992px) {
    .footer-text {
        margin: 0;
    }
}

.badge-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--emerald-600);
    margin-right: 10px;
}

.footer-heading {
    font-weight: 700;
    color: var(--slate-900);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.footer-social a {
    width: 40px;
    height: 40px;
    background: #f8fafc;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--slate-900);
    margin-left: 10px;
    transition: 0.3s;
    border: 1px solid #e2e8f0;
}

.footer-social a:hover {
    background: var(--emerald-500);
    color: white;
    transform: translateY(-3px);
    border-color: var(--emerald-500);
}

.footer-divider {
    border-color: #f1f5f9;
    margin: 2rem 0;
}

.footer-copyright {
    color: #64748b;
    font-size: 0.9rem;
}

.footer-copyright a {
    color: var(--emerald-600);
    font-weight: 700;
}
</style>
<?php endif ?>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Logic preserved exactly as requested
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});

const navbar = document.querySelector('.navbar-custom');
if (navbar) {
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 50) {
            navbar.style.boxShadow = '0 10px 15px -3px rgba(0,0,0,0.05)';
        } else {
            navbar.style.boxShadow = 'none';
        }
    });
}
</script>
</body>

</html>