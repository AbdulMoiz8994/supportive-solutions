<?php

test('the application redirects guests to signin', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('signin'));
});
