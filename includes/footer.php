<?php
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
?>

<?php if ($isLoggedIn): ?>
            </main>
            <!-- Dashboard Footer -->
            <footer class="py-3 px-4 bg-white border-top d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 text-muted small mt-auto">
                <div>
                    <span class="fw-bold text-dark"><i class="bi bi-lightning-charge-fill text-warning"></i> Quizzy</span>
                    &copy; <?php echo date('Y'); ?> Enterprise Educational Assessment Platform
                </div>
                <div>
                    <span class="text-success"><i class="bi bi-shield-check"></i> System Operational</span>
                </div>
            </footer>
        </div>
    </div>
<?php else: ?>
    </main>
    <footer class="app-footer">
        <div class="container text-center">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <span class="text-white fw-bold"><i class="bi bi-lightning-charge-fill text-warning"></i> Quizzy</span>
                <span class="text-muted small">&bull;</span>
                <span class="text-muted small">Smart Online Examination Platform</span>
            </div>
            <p class="small text-muted mb-0">&copy; <?php echo date('Y'); ?> Quizzy. Engineered for academic excellence.</p>
        </div>
    </footer>
<?php endif; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Mobile Sidebar Toggle -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('appSidebar');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    if (closeBtn && sidebar) {
        closeBtn.addEventListener('click', function() {
            sidebar.classList.remove('show');
        });
    }
});
</script>
</body>
</html>
