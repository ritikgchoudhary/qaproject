<?php
// Pagination helper
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

function renderPagination($total_pages, $current_page, $url) {
    if ($total_pages <= 1) return;
    echo '<div class="flex justify-center gap-2 mt-6">';
    for ($i = 1; $i <= $total_pages; $i++) {
        $active = $i == $current_page ? 'bg-yellow-500 text-black' : 'bg-white/5 text-gray-400 hover:text-white';
        echo "<a href='{$url}?page={$i}' class='w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold {$active} transition-all'>{$i}</a>";
    }
    echo '</div>';
}
?>
