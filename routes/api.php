<?php
use App\Controllers\Api\{DeviceMediaApiController,NodeCertificateController,NodeHeartbeatController,TmdbApiController};use App\Middleware\{ApiTokenMiddleware,NodeHmacMiddleware};
$r=$app->router;
$r->get('/api/v1/admin/tmdb/search',[TmdbApiController::class,'search'],[ApiTokenMiddleware::class]);
$r->post('/api/v1/admin/tmdb/select',[TmdbApiController::class,'select'],[ApiTokenMiddleware::class]);
$r->post('/api/v1/device/media-token',[DeviceMediaApiController::class,'issue'],[ApiTokenMiddleware::class]);
$r->post('/api/v1/internal/heartbeat',[NodeHeartbeatController::class,'heartbeat'],[NodeHmacMiddleware::class]);
$r->get('/api/v1/internal/certificates/pending',[NodeCertificateController::class,'pending'],[NodeHmacMiddleware::class]);
$r->post('/api/v1/internal/certificates/result',[NodeCertificateController::class,'result'],[NodeHmacMiddleware::class]);
