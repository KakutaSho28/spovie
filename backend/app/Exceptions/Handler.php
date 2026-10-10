<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use App\Exceptions\ServiceException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Service 層の業務ルール違反を { "message": ... } で返す
        $this->renderable(function (ServiceException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        });

        // Policy の拒否（AuthorizationException → AccessDeniedHttpException）を API 共通の 403 形式で返す
        $this->renderable(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'この操作は許可されていません'], 403);
            }
        });
    }
}
