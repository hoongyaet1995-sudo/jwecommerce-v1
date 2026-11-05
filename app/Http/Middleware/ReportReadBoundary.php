<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Read-only Reports failures must not expose connection details or SQL bindings. */
final class ReportReadBoundary
{
    public function handle(Request $request,Closure $next): Response
    {
        try {
            $response=$next($request);
            // Laravel's routing pipeline may already have rendered the exception.
            if($response->getStatusCode()>=500&&($response->exception??null) instanceof \Throwable)return $this->unavailable($request,$response->exception,!($response->exception instanceof \PDOException));
            return $response;
        }
        catch(QueryException|\PDOException $e) {
            return $this->unavailable($request,$e);
        }
    }
    public static function logFailure(Request $request,\Throwable $exception): void
    {
        Log::warning('reports.read_unavailable',['actor_id'=>$request->user()?->id,'route'=>$request->route()?->uri(),'reason'=>'read_exception','exception_type'=>get_class($exception),'error_code'=>$exception->getCode()]);
    }
    public static function failureResponse(): Response
    {
        return response()->json(['answer_kind'=>'unavailable','message'=>'Report data is temporarily unavailable. Please retry later.'],503)->header('Cache-Control','private, no-store');
    }
    private function unavailable(Request $request,\Throwable $exception,bool $log=true): Response
    {
        if($log)self::logFailure($request,$exception);
        return self::failureResponse();
    }
}
