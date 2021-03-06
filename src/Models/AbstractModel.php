<?php

namespace OpenDominion\Models;

use Illuminate\Database\Eloquent\Model;

class AbstractModel extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $dates = ['created_at', 'updated_at'];
}
