<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('school:summary', function (): void {
    $this->info('THPT Vo Van Kiet management system is installed.');
})->purpose('Show a short school system summary');

