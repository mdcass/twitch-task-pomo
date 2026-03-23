<?php

namespace App\Actions\Canvases\Concerns;

use Illuminate\Support\Facades\Validator;

trait ValidatesCanvasAttributes
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{name: string, width: int, height: int}
     */
    protected function validateCanvasAttributes(array $input): array
    {
        $validated = Validator::make([
            'name' => trim((string) ($input['name'] ?? '')),
            'width' => $input['width'] ?? null,
            'height' => $input['height'] ?? null,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'width' => ['required', 'integer', 'min:1'],
            'height' => ['required', 'integer', 'min:1'],
        ])->validate();

        return [
            'name' => $validated['name'],
            'width' => (int) $validated['width'],
            'height' => (int) $validated['height'],
        ];
    }
}
