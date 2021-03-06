<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PictureRating;

class Picture extends Model
{

    protected $table = 'picture';

    protected $primaryKey = 'id';

    public function getWebPath()
    {
        return 'uploads/images/kebabs/' . $this->id . '.jpg';
    }

    public static function getWebPathPP($idUser) {
        $image = 'uploads/images/users/'.$idUser.'.jpg';
        return file_exists($image) ? $image : 'images/default.png';
    }

    public function getRate()
    {
       return $this->PictureRating()->count();
    }

    public function user()
    {
		return $this->belongsTo('App\Models\User');
	}

    public function pictureRating()
    {
        return $this->hasMany('App\Models\PictureRating');
    }


	public function comments()
    {
        return $this->hasMany('App\Models\Comment');
    }

    public function hashtags()
    {
        return $this->belongsToMany('App\Models\Hashtag')->withPivot('count');
    }
}
