<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    public function timeWindows()
    {
        $this->hasMany(TimeWindow::class);
    }

    public function activeTimeWindows()
    {
        return $this->hasMany(TimeWindow::class)->where('is_available', 1);
    }

    public function inactiveTimeWindows()
    {
        return $this->hasMany(TimeWindow::class)->where('is_available', 0);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
