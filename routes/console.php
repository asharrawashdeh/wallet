<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('wallet:retry-failed')->everyFiveMinutes()->withoutOverlapping();
