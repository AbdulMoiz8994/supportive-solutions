<?php

use Illuminate\Support\Facades\Route;

// TailAdmin / template reference pages (restricted by demo.routes middleware)

Route::get('/template/billing', function () {
    return view('pages.ecommerce.billing', ['title' => 'Billing Template Reference']);
})->name('template.billing');

// AI demo pages
Route::get('/code-generator', function () {
    return view('pages.ai.code-generator', ['title' => 'Code Generator']);
})->name('code-generator');

Route::get('/text-generator', function () {
    return view('pages.ai.text-generator', ['title' => 'Text Generator']);
})->name('text-generator');

Route::get('/image-generator', function () {
    return view('pages.ai.image-generator', ['title' => 'Image Generator']);
})->name('image-generator');

Route::get('/video-generator', function () {
    return view('pages.ai.video-generator', ['title' => 'Video Generator']);
})->name('video-generator');

// Ecommerce demo pages
Route::get('/products-list', function () {
    return view('pages.ecommerce.product-list', ['title' => 'Product List']);
})->name('products-list');

Route::get('/add-product', function () {
    return view('pages.ecommerce.add-product', ['title' => 'Add Product']);
})->name('add-product');

Route::post('/add-product', function () {
    return view('pages.ecommerce.add-product', ['title' => 'Add Product']);
})->name('add-product.store');

Route::get('/invoices', function () {
    return view('pages.ecommerce.invoices', ['title' => 'Invoices']);
})->name('invoices');

Route::get('/single-invoice', function () {
    return view('pages.ecommerce.single-invoice', ['title' => 'Single Invoice']);
})->name('single-invoice');

Route::get('/create-invoice', function () {
    return view('pages.ecommerce.create-invoice', ['title' => 'Create Invoice']);
})->name('create-invoice');

Route::get('/transactions', function () {
    return view('pages.ecommerce.transactions', ['title' => 'Transactions']);
})->name('transactions');

Route::get('/single-transaction', function () {
    return view('pages.ecommerce.single-transaction', ['title' => 'Single Transaction']);
})->name('single-transaction');

// Task demo pages
Route::get('/task-list', function () {
    return view('pages.task.task-list', ['title' => 'Task List']);
})->name('task-list');

Route::get('/task-kanban', function () {
    return view('pages.task.task-kanban', ['title' => 'Task Kanban']);
})->name('task-kanban');

// Form demo pages
Route::get('/form-elements', function () {
    return view('pages.form.form-elements', ['title' => 'Form Elements']);
})->name('form-elements');

Route::get('/form-layout', function () {
    return view('pages.form.form-layout', ['title' => 'Form Layout']);
})->name('form-layout');

// Table demo pages
Route::get('/basic-tables', function () {
    return view('pages.tables.basic-tables', ['title' => 'Basic Tables']);
})->name('basic-tables');

Route::get('/data-tables', function () {
    return view('pages.tables.data-tables', ['title' => 'Data Tables']);
})->name('data-tables');

// Misc demo pages
Route::get('/file-manager', function () {
    return view('pages.file-manager', ['title' => 'File Manager']);
})->name('file-manager');

Route::get('/pricing-tables', function () {
    return view('pages.pricing-tables', ['title' => 'Pricing Tables']);
})->name('pricing-tables');

Route::get('/faq', function () {
    return view('pages.faq', ['title' => 'Faq']);
})->name('faq');

Route::get('/blank', function () {
    return view('pages.blank', ['title' => 'Blank']);
})->name('blank');

Route::get('/integrations', function () {
    return view('pages.integrations', ['title' => 'Integrations']);
})->name('integrations');

// Error demo pages
Route::get('/error-404', function () {
    return view('pages.errors.error-404', ['title' => 'Error 404']);
})->name('error-404');

Route::get('/error-500', function () {
    return view('pages.errors.error-500', ['title' => 'Error 500']);
})->name('error-500');

Route::get('/error-503', function () {
    return view('pages.errors.error-503', ['title' => 'Error 503']);
})->name('error-503');

Route::get('/success', function () {
    return view('pages.success', ['title' => 'Success']);
})->name('success');

Route::get('/coming-soon', function () {
    return view('pages.coming-soon', ['title' => 'Coming Soon']);
})->name('coming-soon');

// Chart demo pages
Route::get('/line-chart', function () {
    return view('pages.chart.line-chart', ['title' => 'Line Chart']);
})->name('line-chart');

Route::get('/bar-chart', function () {
    return view('pages.chart.bar-chart', ['title' => 'Bar Chart']);
})->name('bar-chart');

Route::get('/pie-chart', function () {
    return view('pages.chart.pie-chart', ['title' => 'Pie Chart']);
})->name('pie-chart');

Route::get('/chat', function () {
    return view('pages.chat', ['title' => 'Chat']);
})->name('chat');

// Support demo pages
Route::get('/support-tickets', function () {
    return view('pages.support.support-tickets', ['title' => 'Support Tickets']);
})->name('support-tickets');

Route::get('/support-ticket-reply', function () {
    return view('pages.support.support-ticket-reply', ['title' => 'Support Ticket Reply']);
})->name('support-ticket-reply');

// Email demo pages
Route::get('/inbox', function () {
    return view('pages.email.inbox', ['title' => 'Inbox']);
})->name('inbox');

Route::get('/inbox-details', function () {
    return view('pages.email.inbox-details', ['title' => 'Inbox Details']);
})->name('inbox-details');

// UI element demo pages
Route::get('/alerts', function () {
    return view('pages.ui-elements.alerts', ['title' => 'Alerts']);
})->name('alerts');

Route::get('/avatars', function () {
    return view('pages.ui-elements.avatars', ['title' => 'Avatars']);
})->name('avatars');

Route::get('/badge', function () {
    return view('pages.ui-elements.badges', ['title' => 'Badges']);
})->name('badges');

Route::get('/breadcrumb', function () {
    return view('pages.ui-elements.breadcrumbs', ['title' => 'Breadcrumbs']);
})->name('breadcrumbs');

Route::get('/buttons', function () {
    return view('pages.ui-elements.buttons', ['title' => 'Buttons']);
})->name('buttons');

Route::get('/buttons-group', function () {
    return view('pages.ui-elements.buttons-group', ['title' => 'Buttons Group']);
})->name('buttons-group');

Route::get('/cards', function () {
    return view('pages.ui-elements.cards', ['title' => 'Cards']);
})->name('cards');

Route::get('/carousel', function () {
    return view('pages.ui-elements.carousel', ['title' => 'Carousel']);
})->name('carousel');

Route::get('/dropdowns', function () {
    return view('pages.ui-elements.dropdowns', ['title' => 'Dropdowns']);
})->name('dropdowns');

Route::get('/image', function () {
    return view('pages.ui-elements.images', ['title' => 'Images']);
})->name('images');

Route::get('/links', function () {
    return view('pages.ui-elements.links', ['title' => 'Links']);
})->name('links');

Route::get('/list', function () {
    return view('pages.ui-elements.list', ['title' => 'List']);
})->name('list');

Route::get('/modals', function () {
    return view('pages.ui-elements.modals', ['title' => 'Modals']);
})->name('modals');

Route::get('/notifications', function () {
    return view('pages.ui-elements.notifications', ['title' => 'Notifications']);
})->name('notifications');

Route::get('/pagination', function () {
    return view('pages.ui-elements.pagination', ['title' => 'Pagination']);
})->name('pagination');

Route::get('/popovers', function () {
    return view('pages.ui-elements.popovers', ['title' => 'Popovers']);
})->name('popovers');

Route::get('/progress-bar', function () {
    return view('pages.ui-elements.progress-bar', ['title' => 'Progress Bar']);
})->name('progress-bar');

Route::get('/ribbons', function () {
    return view('pages.ui-elements.ribbons', ['title' => 'Ribbons']);
})->name('ribbons');

Route::get('/spinners', function () {
    return view('pages.ui-elements.spinners', ['title' => 'Spinners']);
})->name('spinners');

Route::get('/tabs', function () {
    return view('pages.ui-elements.tabs', ['title' => 'Tabs']);
})->name('tabs');

Route::get('/tooltips', function () {
    return view('pages.ui-elements.tooltips', ['title' => 'Tooltips']);
})->name('tooltips');

Route::get('/videos', function () {
    return view('pages.ui-elements.videos', ['title' => 'Videos']);
})->name('videos');
