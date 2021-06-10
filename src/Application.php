<?php


namespace Laravel\Worker;


use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Workerman\Protocols\Http as WorkerHttp;

/**
 * Worker应用对象
 */
class Application extends \Illuminate\Foundation\Application
{
    /**
     * 处理Worker请求
     * @access public
     * @param  \Workerman\Connection\TcpConnection   $connection
     * @param  void
     */
    public function worker($connection)
    {
        try {
            while (ob_get_level() > 1) {
                ob_end_clean();
            }

            ob_start();
            $kernel=$this->make(Kernel::class);
            $response = tap($kernel->handle(
                $request = Request::capture()
            ))->send();
            $kernel->terminate($request, $response);
            $content  = ob_get_clean();

            $this->httpResponseCode($response->status());
            $headers=explode("\r\n",(string)$response->headers);
            foreach ($headers as $header) {
                WorkerHttp::header($header);
            }

            if (strtolower($_SERVER['HTTP_CONNECTION']) === "keep-alive") {
                $connection->send($content);
            } else {
                $connection->close($content);
            }
        } catch (HttpResponseException | \Exception | \Throwable $e) {
            $this->exception($connection, $e);
        }
    }

    /**
     * 是否运行在命令行下
     * @return bool
     */
    public function runningInConsole(): bool
    {
        return false;
    }

    protected function httpResponseCode($code = 200)
    {
        WorkerHttp::responseCode($code);
    }

    protected function exception($connection, $e)
    {
        if ($e instanceof \Exception) {
            $handler = $this->make(ExceptionHandler::class);
            $handler->report($e);

            $resp    = $handler->render(Request::capture(), $e);
            $content = $resp->getContent();
            $code = $resp->getStatusCode();

            $this->httpResponseCode($code);
            $connection->send($content);
        } else {
            $this->httpResponseCode(500);
            $connection->send($e->getMessage());
        }
    }

    public function bootstrap()
    {
        $this->singleton(
            \Illuminate\Contracts\Http\Kernel::class,
            \App\Http\Kernel::class
        );

        $this->singleton(
            \Illuminate\Contracts\Console\Kernel::class,
            \App\Console\Kernel::class
        );

        $this->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            \App\Exceptions\Handler::class
        );
        return $this;
    }

}
