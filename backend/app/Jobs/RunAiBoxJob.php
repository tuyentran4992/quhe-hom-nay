<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * BE-0 placeholder — spec 1.mvp/01 §3: CHỖ DUY NHẤT được gọi AI-Box (queue DATABASE).
 * BE-2 implement: nhận AiJob id, timeout 120s, maxAttempts 3 (Rules::AI_*),
 * prompt nhúng luật wording 01 §6. Cấm dispatch khi chưa có body — ctor fail loud.
 */
class RunAiBoxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        throw new \RuntimeException('RunAiBoxJob là placeholder BE-0 — BE-2 cung cấp payload (AiJob id) trước khi dispatch.');
    }

    public function handle(): void
    {
        // BE-2
    }
}
