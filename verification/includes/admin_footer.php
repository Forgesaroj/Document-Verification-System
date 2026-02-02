            </main>

            <!-- Footer -->
            <footer class="bg-white border-t px-6 py-3">
                <p class="text-center text-sm text-gray-500">
                    &copy; <?php echo date('Y'); ?> Company. Verification Portal v1.0
                </p>
            </footer>
        </div>
    </div>

    <script>
        // Auto-hide flash messages
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('[role="alert"]');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });

        // Confirm before delete
        function confirmDelete(message) {
            return confirm(message || 'Are you sure you want to delete this item?');
        }
    </script>
</body>
</html>
