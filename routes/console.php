<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('ops:alerts:evaluate')->hourly();
