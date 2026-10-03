<?php

namespace App\Enums;

enum PostType: string
{
    case ARTICLE = 'article';
    case REVIEW = 'review';
    case COMPARISON = 'comparison';
    case TUTORIAL = 'tutorial';
    case NEWS = 'news';
}