<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriveFolder extends Model
{
    protected $fillable = ['user_id', 'path_hash', 'path', 'gdrive_folder_id'];
}
