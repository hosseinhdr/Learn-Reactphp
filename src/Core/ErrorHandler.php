<?php

declare(strict_types=1);

namespace App\Core;

use App\Helpers\GlobalHelper;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\FulfilledPromise;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Promise\RejectedPromise;
use Throwable;
use function React\Promise\resolve;

final class ErrorHandler
{
    public function __invoke(ServerRequestInterface $request, callable $next): RejectedPromise|PromiseInterface|FulfilledPromise|Promise|Response
    {
        try {
            return resolve($next($request))
                ->then(
                    function (Response $response) use ($request) {
                        $this->writeLog($request, $response);
                        return $response;
                    },
                    function (Throwable $error) use ($request) {
                        $this->writeLog($request, null, $error);
                        return JsonResponse::internalServerError($error->getMessage());
                    }
                );
        } catch (Throwable $error) {
            return JsonResponse::internalServerError($error->getMessage());
        }
    }

    private function writeLog(
        ServerRequestInterface $request,
        Response $response = null,
        Throwable $throwableError = null
    )
    {

        if (!GlobalHelper::$outLoggerStatus)
            return;

        $requestRoute = $request->getUri()->getPath();
        $requesterIp = $this->getClientIP($request);

        $requesterBodyParams = $request->getBody()->getContents();
        $requesterQueryParams = json_encode($request->getQueryParams());

        $finalLog = [
            'type' => 'response',
            'time' => GlobalHelper::getCurrentMicroTime(),
            'request' => [
                'route' => $requestRoute,
                'ip' => $requesterIp,
                'bodyParam' => $requesterBodyParams,
                'queryParam' => $requesterQueryParams
            ]
        ];

        if (is_null($throwableError)) {
            $finalLog['response']['code'] = $response->getStatusCode();
            $finalLog['response']['body'] = $response->getBody()->getContents();
        } else {
            $finalLog['error']['message'] = $throwableError->getMessage();
            $finalLog['error']['trace'] = $throwableError->getTraceAsString();

            Logger::console($finalLog);
        }

        $this->publishLog($finalLog);
    }

    private function getClientIP(ServerRequestInterface $request)
    {
        $serverParams = $request->getServerParams();
        if (!empty($serverParams['HTTP_CLIENT_IP'])) {
            return $serverParams['HTTP_CLIENT_IP'];
        }
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            return $serverParams['HTTP_X_FORWARDED_FOR'];
        }
        return $serverParams['REMOTE_ADDR'];
    }

    private function publishLog(array $finalLog): void
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => GlobalHelper::$outLoggerLink,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 1,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($finalLog, JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        curl_exec($curl);
        curl_close($curl);
    }
}
