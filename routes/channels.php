<?php

use App\Models\Article;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('article.{articleId}', function ($user, $articleId) {
    return Article::where('id', $articleId)
        ->whereHas('site', function ($query) use ($user) {
            $query->where('team_id', $user->team_id);
        })
        ->exists();
});

Broadcast::channel('site.{siteId}', function ($user, $siteId) {
    $site = \App\Models\Site::find($siteId);
    return $site && $site->team_id === $user->team_id;
});
