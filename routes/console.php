<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:prune-batches --hours=48')
    ->daily()
    ->timezone('America/Lima')
    ->withoutOverlapping(1440);

Schedule::command('ops:alerts:evaluate')
    ->hourly()
    ->timezone('America/Lima')
    ->withoutOverlapping(55);
