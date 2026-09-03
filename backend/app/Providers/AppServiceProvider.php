<?php

namespace App\Providers;

use App\Contracts\ParaphraseJudge;
use App\Services\LlmParaphraseJudge;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // QUOTA-N/Q3 (card t_1bb07a82): gan judge that vao DIEM MO RONG Q2 —
        // seam (interface 1 ham, InterpretationService c2.0) doc qua container;
        // flag project.ai.paraphrase_judge van chan o cua (test/ops tat duoc ve
        // hanh vi Q2). Truong hop container khong resolve duoc AiBoxClient
        // (chua cau hinh) — khong the xay ra: AiBoxClient khong co dependency
        // bat buoc. Xoa binding nay = Q2 hoi ve dung nguyen.
        $this->app->bind(ParaphraseJudge::class, LlmParaphraseJudge::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
