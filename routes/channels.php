<?php

use App\Models\Article;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('article.{articleId}', function ($user, $articleId) {
    $article = Article::find($articleId);
    if (!$article) {
        return false;
    }
    return $user->team_id === $article->site->team_id;
});
