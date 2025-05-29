jQuery(document).ready(function ($) {
    const $rows = $("#cron-table tbody tr");
    const $search = $("#cron-search");
    const $pagination = $("#cron-pagination");
    const perPage = 10;
    let currentPage = 1;

    function filterRows() {
        const keyword = $search.val().toLowerCase();
        return $rows.filter(function () {
            return $(this).text().toLowerCase().includes(keyword);
        });
    }

    function renderPage(rows) {
        $rows.hide();
        const start = (currentPage - 1) * perPage;
        rows.slice(start, start + perPage).show();
        $(".current-page").text(currentPage);
        $(".total-pages").text(Math.ceil(rows.length / perPage));
    }

    function renderPagination(rows) {
        $(".first-page").off().on("click", function () {
            currentPage = 1;
            renderPage(rows);
        });
        $(".prev-page").off().on("click", function () {
            if (currentPage > 1) currentPage--;
            renderPage(rows);
        });
        $(".next-page").off().on("click", function () {
            const maxPage = Math.ceil(rows.length / perPage);
            if (currentPage < maxPage) currentPage++;
            renderPage(rows);
        });
        $(".last-page").off().on("click", function () {
            currentPage = Math.ceil(rows.length / perPage);
            renderPage(rows);
        });
    }

    $search.on("input", function () {
        currentPage = 1;
        const visible = filterRows();
        renderPage(visible);
        renderPagination(visible);
    });

    $(".cron-interval-select").on("change", function () {
        const hook = $(this).data("hook");
        const interval = $(this).val();
        $.post(wpCronScheduler.ajax_url, {
            action: "cron_scheduler_update",
            nonce: wpCronScheduler.nonce,
            hook,
            interval,
        });
    });

    const initialRows = filterRows();
    renderPage(initialRows);
    renderPagination(initialRows);
});