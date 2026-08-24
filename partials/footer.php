<?php
/* =========================================================
   GARUDAFOOD SKILL MONITORING
   FOOTER
========================================================= */
?>

        </section>

    </main>

</div>


<!-- =========================================================
     SIDEBAR OVERLAY
========================================================= -->

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<!-- =========================================================
     BOOTSTRAP
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<!-- =========================================================
     CHART.JS
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"
></script>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const sidebar =
            document.getElementById('sidebar');

        const overlay =
            document.getElementById('sidebarOverlay');

        const toggle =
            document.getElementById('sidebarToggle');


        function openSidebar()
        {
            if (!sidebar) return;

            sidebar.classList.add('show');

            if (overlay) {
                overlay.classList.add('show');
            }
        }


        function closeSidebar()
        {
            if (!sidebar) return;

            sidebar.classList.remove('show');

            if (overlay) {
                overlay.classList.remove('show');
            }
        }


        if (toggle) {

            toggle.addEventListener(
                'click',
                function () {

                    if (
                        sidebar &&
                        sidebar.classList.contains('show')
                    ) {

                        closeSidebar();

                    } else {

                        openSidebar();

                    }

                }
            );

        }


        if (overlay) {

            overlay.addEventListener(
                'click',
                function () {

                    closeSidebar();

                }
            );

        }


        document.addEventListener(
            'keydown',
            function (event) {

                if (event.key === 'Escape') {

                    closeSidebar();

                }

            }
        );


        window.addEventListener(
            'resize',
            function () {

                if (
                    window.innerWidth >= 992
                ) {

                    closeSidebar();

                }

            }
        );

    }

);

</script>


</body>

</html>