<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Foundation\Http\Exceptions\MaintenanceModeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
//use Illuminate\Contracts\Validation\UnauthorizedException;
use Illuminate\Auth\Access\UnauthorizedException;
use Illuminate\Auth\AuthenticationException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
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
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (AuthenticationException $e, $request) {
            return response()->json(['success' => false, 'code'=>401, 'error' => $e->getMessage()], 401);
        });
        $this->renderable(function (QueryException $e, $request) {
            return response()->json(['success' => false, 'code'=>501, 'error' => $e->getMessage()], 501);
        });
        $this->renderable(function (MaintenanceModeException $e, $request) {
            return response()->json(['success' => false, 'code'=>503, 'error' => $e->getMessage()], 503);
        });
        $this->renderable(function (ThrottleRequestsException $e, $request) {
            return response()->json(['success' => false, 'code'=>503, 'error' => $e->getMessage()], 503);
        });
        $this->renderable(function (ModelNotFoundException $e, $request) {
            return response()->json(['success' => false, 'code'=>404, 'error' => $e->getMessage()], 404);
        });
        $this->renderable(function (UnauthorizedException $e, $request) {
            return response()->json(['success' => false, 'code'=>403, 'error' => $e->getMessage()], 403);
        });
        $this->renderable(function (MethodNotAllowedHttpException $e, $request) {
            return response()->json(['success' => false, 'code'=>405, 'error' => $e->getMessage()], 405);
        });
        $this->renderable(function (NotFoundHttpException $e, $request) {
            return response()->json(['success' => false, 'code'=>404, 'error' => $e->getMessage()], 404);
        });
        $this->renderable(function (Throwable $e, $request) {
            return response()->json(['success' => false, 'code'=>400, 'error' => $e->getMessage()], 400);
        });
    }

    // protected function unauthenticated($request, AuthenticationException $exception)
    // {
    //     //if ($request->expectsJson()) { 
    //         return response()->json(['status'=>false,'code'=>401,'error'=>'Unauthenticated.'],401);
    //     //}
    // }
}
