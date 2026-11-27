<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$quideiShell = $quideiShell ?? false;
?>
            </div><!-- /.app-content -->

            <?php if ($currentPage != 'login.php'): ?>
            <footer class="app-footer">
                <div class="app-footer-inner">
                    <div class="app-footer-brand">
                        <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
                        <span>QUIDEI EMS</span>
                        <span class="app-footer-sep" aria-hidden="true">·</span>
                        <span class="app-footer-muted">© <?php echo date('Y'); ?>
                            <a href="https://infonile.in" target="_blank" rel="noopener">INFONILE</a></span>
                    </div>
                    <div class="app-footer-meta">
                        <a href="mailto:support@quidei.com" class="app-footer-muted"><i class="bi bi-envelope"
                                aria-hidden="true"></i> support@quidei.com</a>
                        <span class="badge bg-light text-dark fw-normal border">Version 1.0</span>
                    </div>
                </div>
            </footer>
            <?php endif ?>
        </div><!-- /.app-main -->
<?php if ($quideiShell): ?>
    </div><!-- /.app-shell -->
<?php endif; ?>

<style>
.app-footer {
    flex: 0 0 auto;
    margin-top: auto;
    background: #fff;
    border-top: 1px solid var(--slate-200);
}

.app-footer-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .5rem;
    max-width: 1680px;
    margin: 0 auto;
    padding: 1rem .875rem;
    font-size: .82rem;
    text-align: center;
}

@media (min-width: 768px) {
    .app-footer-inner {
        flex-direction: row;
        justify-content: space-between;
        padding: 1rem 1.5rem;
        text-align: left;
    }
}

.app-footer-brand {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    color: var(--slate-900);
    font-weight: 700;
}

.app-footer-brand i {
    color: var(--emerald-500);
}

.app-footer-sep {
    color: #cbd5e1;
}

.app-footer-muted {
    color: var(--slate-500);
    font-weight: 500;
    text-decoration: none;
}

.app-footer a {
    color: var(--emerald-600);
    font-weight: 700;
    text-decoration: none;
}

.app-footer a.app-footer-muted {
    color: var(--slate-500);
    font-weight: 500;
}

.app-footer a:hover {
    text-decoration: underline;
}

.app-footer-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: .75rem;
}
</style>

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

// Top bar shadow once the page scrolls.
(() => {
    const topbar = document.querySelector('.app-topbar');
    if (!topbar) return;
    const update = () => topbar.classList.toggle('is-scrolled', window.scrollY > 8);
    window.addEventListener('scroll', update, {
        passive: true
    });
    update();
})();

// Large screens: collapse the sidebar to an icon rail (remembered per browser).
(() => {
    const btn = document.getElementById('sbCollapseBtn');
    if (!btn) return;
    const root = document.documentElement;
    const icon = btn.querySelector('i');
    const sync = () => {
        const collapsed = root.classList.contains('sb-collapsed');
        btn.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
        btn.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        btn.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        icon.className = 'bi ' + (collapsed ? 'bi-layout-sidebar' : 'bi-layout-sidebar-inset');
    };
    btn.addEventListener('click', () => {
        root.classList.toggle('sb-collapsed');
        try {
            localStorage.setItem('quidei.sidebar', root.classList.contains('sb-collapsed') ? 'collapsed' :
                'expanded');
        } catch (e) {}
        sync();
        // Let DataTables and other width-aware widgets re-measure after the transition.
        setTimeout(() => window.dispatchEvent(new Event('resize')), 260);
    });
    sync();
})();

// Keep the active menu item in view when the sidebar is long.
(() => {
    const nav = document.querySelector('.sb-nav');
    const active = nav && nav.querySelector('.sb-link.active');
    if (!active) return;
    const navBox = nav.getBoundingClientRect();
    const linkBox = active.getBoundingClientRect();
    if (linkBox.bottom > navBox.bottom || linkBox.top < navBox.top) {
        nav.scrollTop += linkBox.top - navBox.top - navBox.height / 2 + linkBox.height / 2;
    }
})();
</script>
</body>

</html>
