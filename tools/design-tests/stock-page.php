<?php
// tools/design-tests/stock-page.php: a test page for hero-tidy.cjs (copied to the harness's website/test/stock.php; never
// shipped): one representative stock photo's label, drawn by the live tv_stock_tag(), over a photo-like background.
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/ui/page-shell.php';
require_once __DIR__ . '/../../app/ui/stock-ui.php';
tv_page_start('Stock test', ['no_crumbs' => true]);
echo '<div style="position:relative;height:420px;background:linear-gradient(135deg,#B45309,#2F6F57 60%,#3E2B1F)">'
   . tv_stock_tag(['id' => 1, 'representative' => true, 'credit' => 'Photo: jplenio on Pixabay', 'author' => 'jplenio', 'source_name' => 'Pixabay', 'label' => 'Stock photo · Representative photo',
                   'licence' => 'Pixabay Content License', 'licence_url' => 'https://pixabay.com/service/license-summary/', 'source_url' => 'https://pixabay.com/'], ['at' => 'bl']) . '</div>';
tv_page_end();
