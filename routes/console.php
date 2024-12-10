<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


Schedule::command('app:add-merlin-stock')->twiceDaily(1, 13)->then(function () {
    Artisan::call('app:update-bc-product');
});
