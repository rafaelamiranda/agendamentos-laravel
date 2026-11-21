<?php

test('the home page leads to the agenda', function () {
    $this->get(route('home'))->assertRedirect('/agenda');
});
