<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('evident:housekeeping')->dailyAt('03:10')->onOneServer()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=168')->dailyAt('03:30');
Schedule::command('horizon:snapshot')->everyFiveMinutes(); // metrik dashboard Horizon
