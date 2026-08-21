<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PageSection extends Model { protected $fillable = ['page_id', 'type', 'content', 'position']; protected function casts(): array { return ['content' => 'array']; } }
