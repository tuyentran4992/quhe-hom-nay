<?php
// PREVIEW-ONLY config — file này KHÔNG commit, không tồn tại ở deploy.
// FREE_DEEP_PREVIEW=true  -> luận sâu free (bật chủ động qua .env, mặc định TẮT).
// FREE_DEEP_PREVIEW=false -> hành vi 29k/paywall nguyên bản đã QA.
return ['free_deep' => env('FREE_DEEP_PREVIEW', false)];
