<?php

namespace App\Http\Requests;

class RequestNextActivityRecommendation extends ActivityIntelligenceIndexRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && ($this->user()?->can('ai.request') ?? false);
    }
}
