<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public marketing testimonial (matches website marketing Testimonial shape).
 */
class PublicTestimonialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleParts = array_filter([
            is_string($this->client_role) ? trim($this->client_role) : '',
            is_string($this->company) ? trim($this->company) : '',
        ]);

        $projectTitle = null;
        if ($this->relationLoaded('project') && $this->project !== null) {
            $projectTitle = is_string($this->project->title) ? $this->project->title : null;
        }

        return [
            'id' => $this->id,
            'quote' => is_string($this->content) ? $this->content : '',
            'authorName' => is_string($this->client_name) ? $this->client_name : '',
            'authorRole' => $roleParts !== [] ? implode(', ', $roleParts) : null,
            'authorAvatar' => null,
            'projectTitle' => $projectTitle,
            'rating' => (int) $this->rating,
            'approved' => (bool) $this->approved,
        ];
    }
}
