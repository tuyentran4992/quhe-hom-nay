<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureDeviceSession;
use App\Models\AiJob;
use App\Models\Device;
use App\Services\InterpretationException;
use App\Services\InterpretationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BE-2 — 03-api #5 (POST /api/ai/interpretations) + #6 (GET /api/ai/jobs/{uuid}).
 * Controller MỎNG: validate nhẹ ở đây, mọi quyết định gate nằm trong
 * InterpretationService (1 class 1 trách nhiệm). Không gọi AI-Box ở đây (01 §2).
 */
class InterpretationController extends Controller
{
    public function __construct(private readonly InterpretationService $service)
    {
    }

    /** #5 — 202 queued | 200 khi job trả về đã done (idempotency replay hoặc cache AC-2). */
    public function store(Request $request): JsonResponse
    {
        /** @var Device */
        $device = $request->attributes->get('device');
        $body = (array) $request->json()->all();

        // rule hình dạng #5: draw_id int>0, topic ∈ C-02, idempotency_key 8–64 (03-api §5)
        $errors = [];
        if (filter_var($body['draw_id'] ?? null, FILTER_VALIDATE_INT) === false || (int) ($body['draw_id'] ?? 0) <= 0) {
            $errors['draw_id'] = ['draw_id phải là số nguyên dương.'];
        }
        if (! is_string($body['topic'] ?? null)) {
            $errors['topic'] = ['topic phải là chuỗi (C-02).'];
        }
        if (! preg_match('/^.{8,64}$/', (string) ($body['idempotency_key'] ?? ''))) {
            $errors['idempotency_key'] = ['idempotency_key bắt buộc 8–64 ký tự.'];
        }
        if ($errors !== []) {
            return InterpretationException::validation($errors)->toResponse();
        }

        try {
            $job = $this->service->request($device, $body);
        } catch (InterpretationException $e) {
            return $e->toResponse();
        }

        // 202 khi job VỚI TẠO MỚI còn queued (mới dispatch); mọi trường hợp còn lại 200:
        // cache AC-2 tạo job mới nhưng done ngay tại chỗ; replay same key = same job (spec §5).
        $status = ($job->wasRecentlyCreated && $job->status === AiJob::ST_QUEUED) ? 202 : 200;

        return response()->json(['data' => [
            'job_uuid' => $job->job_uuid,
            'status' => $job->status,
            'topic' => $job->topic,
            'draw_id' => (int) $job->draw_id,
            'poll_url' => '/api/ai/jobs/'.$job->job_uuid,
        ]], $status);
    }

    /** #6 — poll. uuid lạ hoặc của device khác = 404 (ẩn tồn tại, F7). */
    public function show(Request $request, string $jobUuid): JsonResponse
    {
        /** @var Device */
        $device = $request->attributes->get('device');

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $jobUuid)) {
            return InterpretationException::notFound()->toResponse();
        }
        $job = $this->service->poll($device, $jobUuid);
        if ($job === null) {
            return InterpretationException::notFound()->toResponse();
        }

        return response()->json(['data' => $job->toApi()]);
    }
}
