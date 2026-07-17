<?php

test('web middleware group includes csrf protection', function () {
    $middleware = app(\Illuminate\Routing\Router::class)->getMiddlewareGroups()['web'];

    expect($middleware)->toContain(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
});

test('state changing routes are in web middleware group', function () {
    $route = collect(app(\Illuminate\Routing\Router::class)->getRoutes())
        ->first(fn ($r) => $r->getName() === 'clients.store');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('web');
});
