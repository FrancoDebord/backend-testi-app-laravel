<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Testimony;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ApiResponse;

    /**
     * POST /testimonies/{id}/report
     * Body: { reason: string, comment?: string }
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'reason'  => ['required', 'in:inappropriate_content,false_testimony,hate_speech,spam,other'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $testimony = Testimony::find($id);
        if (!$testimony) {
            return $this->notFound();
        }

        // Idempotent — un seul signalement par utilisateur par témoignage
        Report::updateOrCreate(
            [
                'testimony_id' => $id,
                'reporter_id'  => $request->user()->id,
            ],
            [
                'reason'  => $request->reason,
                'comment' => $request->comment,
            ]
        );

        return $this->success(null, 'Signalement enregistré. Merci pour votre contribution.');
    }
}
